<?php

namespace Vortechron\FilamentCms;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Vortechron\FilamentBlockEditor\Content\PageContent;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentBlockEditor\Design\Kit;
use Vortechron\FilamentCms\Entries\EntryContent;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;

/**
 * The one save path for documents: the page builder, the Filament screens,
 * and later the API and MCP all save here. Each save is checked, upgraded to
 * format 2 and cleaned; when the person saving is not a developer, the
 * stored locks and developer-only data are put back (PageValue::guard());
 * then it is saved with the newest versions kept, in one transaction.
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
    public function save(Document $document, array $value, ?Authenticatable $user, ?bool $developer = null): array
    {
        $isEntry = $document->kind === Document::ENTRY;
        $value = $isEntry ? EntryContent::merge($document, $value) : $value;
        $errors = match (true) {
            $document->kind === Document::KIT => $this->kitErrors($value),
            $isEntry => EntryContent::errors($document, $value) ?: $this->pageErrors($document, $value),
            default => $this->pageErrors($document, $value),
        };

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $developer ??= self::isDeveloper($user);

        DB::transaction(function () use ($document, $value, $user, $developer, $isEntry): void {
            $clean = $document->kind === Document::KIT ? Kit::normalize($value) : PageValue::normalize($value);

            if ($document->kind !== Document::KIT && ! $developer) {
                $clean = PageValue::guard($clean, PageValue::normalize($this->storedContent($document)));
            }

            if ($isEntry) {
                $clean = EntryContent::clean($document, $value, $clean);
            }

            $document->forceFill(['content' => $clean])->save();
            $this->keepVersion($document->revisionKey(), $clean, $user);
        });

        return ['ok' => true];
    }

    /**
     * Makes an older version the current one. An entry gets the version's
     * sections back and keeps its stored fields. The restore is itself saved
     * as a new version, so it can be undone too.
     *
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function restore(Document $document, CmsRevision $revision, ?Authenticatable $user, ?bool $developer = null): array
    {
        abort_unless($revision->key === $document->revisionKey(), 404);

        $content = is_array($revision->content) ? $revision->content : [];

        return $this->save($document, $document->kind === Document::ENTRY ? ['sections' => Document::sectionsOf($content)] : $content, $user, $developer);
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
     * @param  array<mixed>  $value
     * @return array<string, string>
     */
    private function pageErrors(Document $document, array $value): array
    {
        $upgraded = PageValue::upgrade($value);
        $errors = self::errors(['value' => $upgraded], PageValue::rules('value'), 'value');

        if ($errors === [] && $document->cmsKind()?->holdsOneSection() === true) {
            $sections = $upgraded['sections'];

            if (count($sections) > 1 || (isset($sections[0]) && ($sections[0]['type'] ?? null) !== PageContent::TYPE)) {
                $errors['sections'] = __('filament-cms::documents.one_section');
            }
        }

        return $errors;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, string>
     */
    private function kitErrors(array $value): array
    {
        return self::errors(['kit' => $value], Kit::rules('kit'), 'kit');
    }

    /**
     * The first message of each failing rule, keyed without the outer name.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @return array<string, string>
     */
    private static function errors(array $data, array $rules, string $prefix): array
    {
        $validator = Validator::make($data, $rules);

        if (! $validator->fails()) {
            return [];
        }

        $errors = [];

        foreach ($validator->errors()->messages() as $key => $messages) {
            $errors[$key === $prefix ? $key : substr($key, strlen($prefix) + 1)] = (string) ($messages[0] ?? '');
        }

        return $errors;
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
