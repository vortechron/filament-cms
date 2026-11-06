<?php

namespace Vortechron\FilamentCms;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Vortechron\FilamentBlockEditor\Rendering\ContentLocale;
use Vortechron\FilamentCms\Documents\ContentHandler;
use Vortechron\FilamentCms\Documents\PageValueContent;
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
    public function save(Document $document, array $value, ?Authenticatable $user, ?bool $developer = null): array
    {
        return ContentLocale::with($document->language(), fn (): array => $this->saveInLanguage($document, $value, $user, $developer));
    }

    private function saveInLanguage(Document $document, array $value, ?Authenticatable $user, ?bool $developer): array
    {
        if (SiteLanguages::enabled() && $document->kind === Document::PAGE && $document->path !== null) {
            $path = SiteLanguages::path($document->path, $document->language());
            if (Document::pathTaken($path, $document)) {
                return ['ok' => false, 'errors' => ['path' => __('filament-cms::pages.path_taken')]];
            }
            $document->path = $path;
        }
        $content = self::content($document);
        $value = $content->merge($document, $value);
        $errors = $content->errors($document, $value);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $developer ??= self::isDeveloper($user);

        DB::transaction(function () use ($document, $content, $value, $user, $developer): void {
            $clean = $content->clean($document, $value, fn (): array => $this->storedContent($document), $developer);

            $document->forceFill(['content' => $clean])->save();
            $this->keepVersion($document->revisionKey(), $clean, $user);
        });

        return ['ok' => true];
    }

    /**
     * Makes an older version the current one (an entry gets the version's
     * sections back and keeps its stored fields). The restore is itself
     * saved as a new version, so it can be undone too.
     *
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function restore(Document $document, CmsRevision $revision, ?Authenticatable $user, ?bool $developer = null): array
    {
        abort_unless($revision->key === $document->revisionKey(), 404);

        return $this->save($document, self::content($document)->restored(is_array($revision->content) ? $revision->content : []), $user, $developer);
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
