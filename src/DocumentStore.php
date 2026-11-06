<?php

namespace Vortechron\FilamentCms;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Vortechron\FilamentBlockEditor\Content\TextTranslations;
use Vortechron\FilamentBlockEditor\Rendering\ContentLocale;
use Vortechron\FilamentCms\Documents\ContentHandler;
use Vortechron\FilamentCms\Documents\PageValueContent;
use Vortechron\FilamentCms\Documents\RestoreIdentities;
use Vortechron\FilamentCms\Documents\ValidationErrors;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;

/**
 * The one save path for documents: the page builder, the Filament screens,
 * EntryStore and the MCP server all save here. Each kind's content handler
 * (CmsKind::content(): a page value, the kit, or an entry's fields and
 * sections) merges what is sent with what it keeps, checks it, and cleans
 * it; when the person saving is not a developer, the stored locks and
 * developer-only data are put back (PageValue::guard()). Then it is saved
 * with the newest versions kept, in one transaction.
 *
 *     app(DocumentStore::class)->save($page, $value, $user);   // ['ok' => true] or ['ok' => false, 'errors' => [...]]
 */
final class DocumentStore
{
    /**
     * @param  array<mixed>  $value  a page value of any format, a kit for kind "kit", or for kind "entry" `fields` and/or `sections` (see EntryContent)
     * @param  bool|null  $developer  who may change locks; null asks the panel's plugin (false outside a panel)
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function save(Document $document, array $value, ?Authenticatable $user, ?bool $developer = null, ?int $expectedVersion = null): array
    {
        try {
            return DB::transaction(function () use ($document, $value, $user, $developer, $expectedVersion): array {
                $root = $document->exists ? Document::query()->lockForUpdate()->findOrFail($document->translationRootId()) : null;
                if ($root?->shared_format) {
                    if ($error = $this->versionError($root, $expectedVersion)) {
                        return $error;
                    }

                    return $this->saveShared($document, $root, $value, $user, $developer ?? self::isDeveloper($user));
                }

                return ContentLocale::with($document->language(), fn (): array => $this->saveInLanguage($document, $value, $user, $developer));
            });
        } catch (InvalidArgumentException $error) {
            return ['ok' => false, 'errors' => ['content' => $error->getMessage()]];
        }
    }

    /** Metadata changes use the same fresh root lock as text and layout saves. */
    public function saveMetadata(Document $document, array $changes, ?Authenticatable $user, ?int $expectedVersion = null): array
    {
        return DB::transaction(function () use ($document, $changes, $expectedVersion): array {
            $root = Document::query()->lockForUpdate()->findOrFail($document->translationRootId());
            if ($root->shared_format && ($error = $this->versionError($root, $expectedVersion))) {
                return $error;
            }
            $maps = $changes['translations'] ?? [];
            unset($changes['translations']);
            if (! is_array($maps) || ($maps !== [] && ($document->translation_of || ! $root->shared_format))) {
                return ['ok' => false, 'errors' => ['translations' => 'Edit language metadata on the converted root document.']];
            }
            $stored = Document::findOrFail($document->getKey());
            $pending = [[$stored, $changes]];
            foreach ($maps as $locale => $meta) {
                if (! isset(SiteLanguages::labels()[$locale]) || $locale === $root->language() || ! is_array($meta)) {
                    return ['ok' => false, 'errors' => ['translations' => 'Choose a configured secondary language and metadata fields.']];
                }
                if ($meta === []) {
                    continue;
                }
                $row = $root->translation($locale) ?? new Document([
                    'kind' => $root->kind, 'entry_type' => $root->entry_type, 'translation_of' => $root->id,
                    'locale' => $locale, 'title' => $root->title,
                    'path' => $root->path === null ? null : SiteLanguages::path($changes['path'] ?? $root->path, $locale),
                    'is_published' => false, 'content' => [], 'text_overrides' => [],
                ]);
                $pending[] = [$row, $meta];
            }
            $paths = [];
            foreach ($pending as [$row, $meta]) {
                if ($errors = $this->metadataErrors($meta)) {
                    return ['ok' => false, 'errors' => $errors];
                }
                $row->forceFill($meta);
                if ($error = $this->pathError($row)) {
                    return $error;
                }
                if ($row->path !== null && isset($paths[$row->path])) {
                    return ['ok' => false, 'errors' => ['path' => 'Each language needs a different address.']];
                }
                if ($row->path !== null) {
                    $paths[$row->path] = true;
                }
            }
            foreach ($pending as [$row, $meta]) {
                $row->save();
            }
            if ($root->shared_format) {
                $root->increment('edit_version');
            }
            $document->refresh();

            return ['ok' => true, ...($root->shared_format ? ['edit_version' => $root->edit_version] : [])];
        });
    }

    private function metadataErrors(array $changes): array
    {
        $allowed = ['title', 'path', 'is_published', 'sort', 'seo_title', 'seo_description'];
        if (array_diff(array_keys($changes), $allowed)) {
            return ['metadata' => 'Only title, path, publication, sort and SEO can be changed here.'];
        }

        return ValidationErrors::of('metadata', $changes, [
            'metadata.title' => ['sometimes', 'string', 'max:120'],
            'metadata.path' => ['sometimes', 'nullable', 'string', 'max:120'],
            'metadata.is_published' => ['sometimes', 'boolean'],
            'metadata.sort' => ['sometimes', 'integer'],
            'metadata.seo_title' => ['sometimes', 'nullable', 'string', 'max:70'],
            'metadata.seo_description' => ['sometimes', 'nullable', 'string', 'max:170'],
        ]);
    }

    private function versionError(Document $root, ?int $expectedVersion): ?array
    {
        if ($root->shared_format !== 1 || $expectedVersion === null || $expectedVersion !== $root->edit_version) {
            return ['ok' => false, 'errors' => ['expected_version' => __('filament-cms::languages.document_changed')]];
        }

        return null;
    }

    private function saveShared(Document $document, Document $root, array $value, ?Authenticatable $user, bool $developer): array
    {
        $translations = app(DocumentTranslations::class);
        $stored = $root->content ?? [];
        $handler = self::content($root);
        $errors = $handler->errors($root, $value);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $meta = array_intersect_key($document->getDirty(), array_flip(['title', 'path', 'is_published', 'sort', 'seo_title', 'seo_description']));
        if ($errors = $this->metadataErrors($meta)) {
            return ['ok' => false, 'errors' => $errors];
        }
        $selected = Document::query()->findOrFail($document->getKey());
        $selected->forceFill($meta);
        if ($error = $this->pathError($selected)) {
            return $error;
        }
        try {
            if ($document->translation_of) {
                if (array_key_exists('translations', $value)) {
                    throw new InvalidArgumentException('A language document accepts only its own text. Edit the root document for shared layout or other languages.');
                }
                $localized = $translations->resolvedValue($selected);
                $clean = $developer ? $value : $handler->clean($selected, $value, fn (): array => $localized, false);
                $selected->text_overrides = TextTranslations::extractForEdit($stored, $translations->logicalLinks($clean), $selected->text_overrides ?? []);
            } else {
                $baseline = $translations->editorValue($root);
                $given = $value['translations'] ?? [];
                if (! is_array($given)) {
                    throw new InvalidArgumentException('Translations must be language text maps.');
                }
                foreach ($given as $locale => $map) {
                    if (! isset(SiteLanguages::labels()[$locale]) || $locale === $root->language() || ! is_array($map)) {
                        throw new InvalidArgumentException('Choose a configured secondary language and a text map.');
                    }
                }
                $incoming = [...$value, 'translations' => array_replace($baseline['translations'] ?? [], $given)];
                $clean = ContentLocale::with($root->language(), fn (): array => $handler->clean($root, $incoming, fn (): array => $baseline, $developer));
                $maps = $clean['translations'] ?? [];
                $clean = TextTranslations::ensureIds($translations->logicalLinks($clean));
                if (! is_array($maps)) {
                    throw new InvalidArgumentException('Translations must be language text maps.');
                }
                $rows = $root->translations();
                foreach ($maps as $locale => $map) {
                    if (! isset(SiteLanguages::labels()[$locale]) || $locale === $root->language() || ! is_array($map)) {
                        throw new InvalidArgumentException('Choose a configured secondary language and a text map.');
                    }
                }
                $pending = [];
                foreach ($rows as $row) {
                    if ($row->getKey() === $root->getKey() || ! array_key_exists($row->language(), $maps)) {
                        continue;
                    }
                    $next = TextTranslations::normalizeOverrides($clean, $maps[$row->language()]);
                    $pending[] = [$row, $next];
                }
                // Validate everything before saving any language.
                foreach ($maps as $locale => $map) {
                    if ($rows->contains(fn (Document $row): bool => $row->language() === $locale)) {
                        continue;
                    }
                    $path = $root->path === null ? null : SiteLanguages::path($root->path, $locale);
                    if ($path !== null && Document::pathTaken($path)) {
                        throw new InvalidArgumentException('Another page already has the translated address '.$path.'.');
                    }
                    $row = new Document(['kind' => $root->kind, 'entry_type' => $root->entry_type, 'locale' => $locale, 'translation_of' => $root->id, 'title' => $root->title, 'path' => $path, 'is_published' => false, 'content' => []]);
                    $pending[] = [$row, TextTranslations::normalizeOverrides($clean, $map)];
                }
                foreach ($pending as [$row, $next]) {
                    if ($row->exists && ($row->text_overrides ?? []) === $next) {
                        continue;
                    }
                    $row->forceFill(['text_overrides' => $next, 'content' => []])->save();
                    $this->keepVersion($row->revisionKey(), ['shared_format' => 1, 'text_overrides' => $next], $user);
                }
                $root->content = $clean;
                $selected = $root->forceFill($meta);
            }
        } catch (InvalidArgumentException $error) {
            return ['ok' => false, 'errors' => ['content' => $error->getMessage()]];
        }
        $selected->save();
        $this->keepVersion($selected->revisionKey(), $selected->translation_of ? ['shared_format' => 1, 'text_overrides' => $selected->text_overrides] : $selected->content, $user);
        $root->increment('edit_version');
        $document->refresh();

        return ['ok' => true, 'edit_version' => $root->edit_version];
    }

    private function pathError(Document $document): ?array
    {
        if (SiteLanguages::enabled() && $document->kind === Document::PAGE && $document->path !== null) {
            $document->path = SiteLanguages::path($document->path, $document->language());
            if (Document::pathTaken($document->path, $document)) {
                return ['ok' => false, 'errors' => ['path' => __('filament-cms::pages.path_taken')]];
            }
        }

        return null;
    }

    private function saveInLanguage(Document $document, array $value, ?Authenticatable $user, ?bool $developer): array
    {
        if ($error = $this->pathError($document)) {
            return $error;
        }
        $initialize = ! $document->exists && ! $document->translation_of && SiteLanguages::enabled()
            && $document->cmsKind()?->holdsSections() === true && $document->kind !== Document::ENTRY
            && Schema::hasColumns($document->getTable(), ['shared_format', 'text_overrides', 'edit_version']);
        if ($initialize && isset($value['translations'])) {
            if (! is_array($value['translations'])) {
                return ['ok' => false, 'errors' => ['translations' => 'Translations must be language text maps.']];
            }
            foreach ($value['translations'] as $locale => $map) {
                if (! isset(SiteLanguages::labels()[$locale]) || $locale === $document->language() || ! is_array($map)) {
                    return ['ok' => false, 'errors' => ['translations' => 'Choose a configured secondary language and a text map.']];
                }
                if ($document->path !== null && Document::pathTaken(SiteLanguages::path($document->path, $locale))) {
                    return ['ok' => false, 'errors' => ['path' => 'Another page already has the translated address.']];
                }
            }
        }
        $content = self::content($document);
        $value = $content->merge($document, $value);
        $errors = $content->errors($document, $value);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $developer ??= self::isDeveloper($user);

        DB::transaction(function () use ($document, $content, $value, $user, $developer, $initialize): void {
            $clean = $content->clean($document, $value, fn (): array => $this->storedContent($document), $developer);

            $maps = $initialize ? ($clean['translations'] ?? []) : [];
            if ($initialize) {
                $clean = TextTranslations::ensureIds($clean);
                $document->forceFill(['shared_format' => 1, 'edit_version' => 1]);
            }
            $document->forceFill(['content' => $clean])->save();
            $this->keepVersion($document->revisionKey(), $clean, $user);
            foreach ($maps as $locale => $overrides) {
                $row = Document::create([
                    'kind' => $document->kind, 'entry_type' => $document->entry_type,
                    'translation_of' => $document->getKey(), 'locale' => $locale,
                    'title' => $document->title, 'path' => $document->path === null ? null : SiteLanguages::path($document->path, $locale),
                    'is_published' => false, 'content' => [], 'text_overrides' => TextTranslations::normalizeOverrides($clean, $overrides),
                ]);
                $this->keepVersion($row->revisionKey(), ['shared_format' => 1, 'text_overrides' => $row->text_overrides], $user);
            }
        });

        return ['ok' => true, ...($initialize ? ['edit_version' => 1] : [])];
    }

    /**
     * Makes an older version the current one (an entry gets the version's
     * sections back and keeps its stored fields). The restore is itself
     * saved as a new version, so it can be undone too.
     *
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function restore(Document $document, CmsRevision $revision, ?Authenticatable $user, ?bool $developer = null, ?int $expectedVersion = null): array
    {
        abort_unless($revision->key === $document->revisionKey(), 404);

        return DB::transaction(function () use ($document, $revision, $user, $developer, $expectedVersion): array {
            $root = Document::query()->lockForUpdate()->findOrFail($document->translationRootId());
            $value = is_array($revision->content) ? $revision->content : [];
            if ($root->shared_format) {
                if ($error = $this->versionError($root, $expectedVersion)) {
                    return $error;
                }
                if ($document->translation_of) {
                    try {
                        $next = ContentLocale::with($document->language(), function () use ($document, $root, $value, $user, $developer): array {
                            $modern = ($value['shared_format'] ?? null) === 1;
                            $storedOverrides = $document->fresh()->text_overrides ?? [];
                            $next = $modern
                                ? TextTranslations::normalizeOverrides($root->content, $value['text_overrides'] ?? [])
                                : TextTranslations::extract($root->content, app(DocumentTranslations::class)->logicalLinks($value));
                            $isDeveloper = $developer ?? self::isDeveloper($user);
                            if ($modern) {
                                $locale = $document->language();
                                if (! $isDeveloper) {
                                    $target = [...$root->content, 'translations' => [$locale => $next]];
                                    $baseline = [...$root->content, 'translations' => [$locale => $storedOverrides]];
                                    $guarded = self::content($root)->clean($document, $target, fn (): array => $baseline, false);
                                    $next = $guarded['translations'][$locale] ?? [];
                                }
                                $next = TextTranslations::restoreOverrides($root->content, $next, $storedOverrides);
                            } elseif (! $isDeveloper) {
                                $candidate = self::content($root)->clean($document, TextTranslations::apply($root->content, $next),
                                    fn (): array => app(DocumentTranslations::class)->resolvedValue($document->fresh()), false);
                                $next = TextTranslations::extract($root->content, $candidate);
                            }

                            return $next;
                        });
                    } catch (InvalidArgumentException $error) {
                        return ['ok' => false, 'errors' => ['revision' => __('filament-cms::languages.restore_translation', [], ContentLocale::panel())]];
                    }
                    $document->refresh()->forceFill(['content' => [], 'text_overrides' => $next])->save();
                    $this->keepVersion($document->revisionKey(), ['shared_format' => 1, 'text_overrides' => $next], $user);
                    $root->increment('edit_version');

                    return ['ok' => true, 'edit_version' => $root->edit_version];
                }
                try {
                    $value = ContentLocale::with($root->language(), fn (): array => app(RestoreIdentities::class)->recover($value, $root->content));
                } catch (InvalidArgumentException|\TypeError $error) {
                    return ['ok' => false, 'errors' => ['revision' => __('filament-cms::languages.restore_identity', [], ContentLocale::panel())]];
                }
                unset($value['translations']);
            }

            return $this->save($document, self::content($document)->restored($value), $user, $developer, $expectedVersion);
        });
    }

    /**
     * In a panel with this plugin, its `developers()` rule; elsewhere nobody.
     */
    public static function isDeveloper(?Authenticatable $user): bool
    {
        $panel = Filament::getCurrentPanel();

        if ($user === null || $panel === null || ! $panel->hasPlugin(FilamentCmsPlugin::ID)) {
            return false;
        }

        /** @var FilamentCmsPlugin $plugin */
        $plugin = $panel->getPlugin(FilamentCmsPlugin::ID);

        return $plugin->isDeveloper($user);
    }

    /**
     * A document of a kind this version does not know is saved as a page value.
     */
    private static function content(Document $document): ContentHandler
    {
        return $document->cmsKind()?->content() ?? app(PageValueContent::class);
    }

    /**
     * What is stored now, read inside the transaction.
     *
     * @return array<mixed>
     */
    private function storedContent(Document $document): array
    {
        if (! $document->exists) {
            return [];
        }

        $content = Document::query()->whereKey($document->getKey())->lockForUpdate()->value('content');

        return is_array($content) ? $content : [];
    }

    /**
     * Saves a version unless it is the same as the newest one, then keeps
     * only the newest `filament-cms.revisions`.
     *
     * @param  array<mixed>  $content
     */
    private function keepVersion(string $key, array $content, ?Authenticatable $user): void
    {
        $latest = CmsRevision::query()->where('key', $key)->latest('id')->value('content');

        if ($latest === $content) {
            return;
        }

        CmsRevision::query()->create(['key' => $key, 'content' => $content, 'user_id' => $user?->getAuthIdentifier()]);

        $keep = max(1, (int) config('filament-cms.revisions', 20));
        $newest = CmsRevision::query()->where('key', $key)->latest('id')->limit($keep)->pluck('id');
        CmsRevision::query()->where('key', $key)->whereNotIn('id', $newest)->delete();
    }
}
