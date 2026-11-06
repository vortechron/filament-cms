<?php

namespace Vortechron\FilamentCms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentBlockEditor\Content\TextTranslations;
use Vortechron\FilamentBlockEditor\Forms\FormDefinition;
use Vortechron\FilamentBlockEditor\Rendering\ContentLocale;
use Vortechron\FilamentCms\Models\Document;

/** One layout on the root; linked rows keep language identity, metadata and text. */
final class DocumentTranslations
{
    public function root(Document $document): Document
    {
        return $document->translation_of ? Document::findOrFail($document->translationRootId()) : $document;
    }

    public function editorValue(Document $document): array
    {
        $root = $this->root($document);
        if (! $root->shared_format) {
            return ContentLocale::with($document->language(), fn (): array => PageValue::normalize($document->content ?? []));
        }
        $value = $root->content ?? [];
        $translations = [];
        foreach ($root->translations() as $row) {
            if ($row->getKey() !== $root->getKey()) {
                $translations[$row->language()] = TextTranslations::normalizeOverrides($value, $row->text_overrides ?? []);
            }
        }

        return [...$value, 'translations' => $translations];
    }

    public function resolvedValue(Document $document, ?string $locale = null): array
    {
        $root = $this->root($document);
        $locale ??= $document->kind === Document::ENTRY ? SiteLanguages::current() : $document->language();
        $selected = $document->kind === Document::ENTRY || $locale === $document->language() ? $document : ($root->translation($locale) ?? $root);
        if (! $root->shared_format) {
            $value = ContentLocale::with($selected->language(), fn (): array => PageValue::upgrade($selected->content ?? []));

            return $document->kind === Document::ENTRY && isset($value['translations'][$locale]) ? TextTranslations::apply($value, $value['translations'][$locale]) : $value;
        }

        $value = TextTranslations::apply($root->content ?? [], $selected->translation_of ? ($selected->text_overrides ?? []) : []);
        if ($locale === $root->language()) {
            return $value;
        }
        $paths = [];
        foreach (Document::query()->whereNull('translation_of')->whereNotNull('path')->get() as $target) {
            $counterpart = $target->translation($locale);
            if ($counterpart?->is_published && $counterpart->path !== null) {
                $paths[$target->path] = $counterpart->path;
            }
        }

        return $this->rewriteLinks($value, $paths);
    }

    /** Fresh comparisons and every write share one root lock. No revision is rewritten. */
    public function checkConversion(Document $document): array
    {
        return $this->conversion($document, false);
    }

    public function convert(Document $document): array
    {
        return $this->conversion($document, true);
    }

    private function conversion(Document $document, bool $write): array
    {
        return DB::transaction(function () use ($document, $write): array {
            $root = Document::query()->lockForUpdate()->findOrFail($document->translationRootId());
            if ($root->shared_format === 1) {
                return ['ok' => true, 'converted' => false];
            }
            if ($root->shared_format !== null || $root->cmsKind()?->holdsSections() !== true || $root->kind === Document::ENTRY || $root->language() !== SiteLanguages::default()) {
                return ['ok' => false, 'errors' => ['document' => 'This document cannot be converted to shared text.']];
            }
            $rows = $root->translations()->sortBy('id');
            $updates = [];
            try {
                $raw = $this->logicalLinks($root->content ?? []);
                ContentLocale::with($root->language(), fn (): array => TextTranslations::extract($raw, $raw));
                $base = ContentLocale::with($root->language(), fn (): array => TextTranslations::ensureIds($raw));
                foreach ($rows as $row) {
                    if ($row->getKey() === $root->getKey()) {
                        continue;
                    }
                    if (! isset(SiteLanguages::labels()[$row->language()]) || $row->kind !== $root->kind || $row->entry_type !== $root->entry_type) {
                        throw new InvalidArgumentException('Unknown language or conflicting document kind.');
                    }
                    $translated = ContentLocale::with($row->language(), fn (): array => $this->logicalLinks($row->content ?? []));
                    $updates[$row->getKey()] = ContentLocale::with($row->language(), fn (): array => TextTranslations::extract($base, $translated));
                }
            } catch (InvalidArgumentException $error) {
                return ['ok' => false, 'errors' => ['content' => 'Shared layout conversion refused: '.$error->getMessage()]];
            }
            if (! $write) {
                return ['ok' => true, 'converted' => true];
            }
            foreach ($rows as $row) {
                if (array_key_exists($row->getKey(), $updates)) {
                    $row->forceFill(['content' => [], 'text_overrides' => $updates[$row->getKey()]])->save();
                }
            }
            $root->forceFill(['content' => $base, 'shared_format' => 1, 'edit_version' => 1])->save();
            $document->refresh();

            return ['ok' => true, 'converted' => true];
        });
    }

    /** Equivalent links to known language counterparts share their root destination. */
    public function logicalLinks(array $value): array
    {
        $paths = Document::query()->whereNotNull('path')->get()->mapWithKeys(function (Document $row): array {
            $root = $this->root($row);

            return $root->path === null ? [] : [$row->path => $root->path];
        })->all();

        return $this->rewriteLinks($value, $paths);
    }

    private function rewriteLinks(array $value, array $paths): array
    {
        $url = static function (mixed $value) use ($paths): mixed {
            if (! is_string($value) || ! str_starts_with($value, '/') || str_starts_with($value, '//')) {
                return $value;
            }
            $length = strcspn($value, '?#');
            $path = substr($value, 0, $length);

            return isset($paths[$path]) ? $paths[$path].substr($value, $length) : $value;
        };
        $fields = function (array $values, array $definitions) use (&$fields, $url): array {
            foreach ($definitions as $field) {
                if ($field->type === 'link' && array_key_exists($field->name, $values)) {
                    $values[$field->name] = $url($values[$field->name]);
                } elseif ($field->type === 'list' && is_array($values[$field->name] ?? null)) {
                    $nested = $field->toArray()['fields'] ?? [];
                    // List definitions use the same serialized shape as the editor.
                    $map = function (array $row, array $specs) use (&$map, $url): array {
                        foreach ($specs as $spec) {
                            $key = $spec['name'];
                            if ($spec['type'] === 'link' && array_key_exists($key, $row)) {
                                $row[$key] = $url($row[$key]);
                            } elseif ($spec['type'] === 'list' && is_array($row[$key] ?? null)) {
                                $row[$key] = array_map(fn (array $child): array => $map($child, $spec['fields'] ?? []), $row[$key]);
                            }
                        }

                        return $row;
                    };
                    $values[$field->name] = array_map(fn (array $row): array => $map($row, $nested), $values[$field->name]);
                }
            }

            return $values;
        };
        $walk = function (array $node) use (&$walk, $url, $fields): array {
            if (($node['type'] ?? null) === 'ownBlock') {
                $definition = app(BlockRegistry::class)->get($node['props']['kind'] ?? '');
                $values = json_decode($node['props']['values'] ?? '{}', true);
                if ($definition && is_array($values)) {
                    $node['props']['values'] = json_encode($fields($values, $definition->getFields()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            if (($node['type'] ?? null) === 'form' && is_string($node['props']['form'] ?? null)) {
                $form = FormDefinition::decode($node['props']['form']);
                $changed = false;
                foreach (is_array($form['actions'] ?? null) ? $form['actions'] : [] as $index => $action) {
                    if (is_array($action) && ($action['type'] ?? null) === 'redirect' && array_key_exists('url', $action)) {
                        $target = $url($action['url']);
                        if ($target !== $action['url']) {
                            $form['actions'][$index]['url'] = $target;
                            $changed = true;
                        }
                    }
                }
                if ($changed) {
                    $node['props']['form'] = FormDefinition::encode($form);
                }
            }
            foreach ($node as $key => &$child) {
                if (is_array($child)) {
                    $child = $walk($child);
                } elseif ($key === 'href' || ($key === 'url' && ! in_array($node['type'] ?? null, ['image', 'video', 'audio', 'file'], true))) {
                    $child = $url($child);
                }
            }
            unset($child);

            return $node;
        };

        return $walk($value);
    }

    public function create(Document $source, string $locale, ?Authenticatable $user = null): Document
    {
        SiteLanguages::check($locale);
        if (! SiteLanguages::enabled() || in_array($source->kind, [Document::KIT, Document::ENTRY], true)) {
            throw ValidationException::withMessages(['locale' => 'This document does not have separate language versions.']);
        }

        return DB::transaction(function () use ($source, $locale, $user): Document {
            $root = Document::query()->lockForUpdate()->findOrFail($source->translationRootId());
            if ($existing = $root->translation($locale)) {
                return $existing;
            }
            $source = $source->fresh();
            $copy = $source->replicate(['path', 'is_published', 'locale', 'translation_of', 'shared_format', 'edit_version', 'text_overrides']);
            $copy->locale = $locale;
            $copy->translation_of = $root->getKey();
            $copy->is_published = false;
            $copy->path = $source->path === null ? null : SiteLanguages::path($source->path, $locale);
            if ($copy->path !== null && Document::pathTaken($copy->path)) {
                throw ValidationException::withMessages(['path' => 'Another page already has the translated address.']);
            }
            if ($root->shared_format) {
                $copy->content = [];
                $copy->text_overrides = $source->translation_of ? ($source->text_overrides ?? []) : [];
                $copy->save();
                $root->increment('edit_version');

                return $copy;
            }
            $result = app(DocumentStore::class)->save($copy, $source->content ?? [], $user, developer: true);
            if (! $result['ok']) {
                throw ValidationException::withMessages($result['errors']);
            }

            return $copy;
        });
    }
}
