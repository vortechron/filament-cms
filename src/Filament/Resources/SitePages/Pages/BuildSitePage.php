<?php

namespace Vortechron\FilamentCms\Filament\Resources\SitePages\Pages;

use Closure;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Vortechron\FilamentBlockEditor\Design\KitSource;
use Vortechron\FilamentBlockEditor\Enums\DocumentKind;
use Vortechron\FilamentBlockEditor\Filament\Concerns\InteractsWithPageBuilder;
use Vortechron\FilamentCms\Access\CmsAccess;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Entries\EntryBuilder;
use Vortechron\FilamentCms\Entries\EntryList;
use Vortechron\FilamentCms\Filament\Resources\Entries\EntryResource;
use Vortechron\FilamentCms\Filament\Resources\SitePages\SitePageResource;
use Vortechron\FilamentCms\Filament\Resources\SiteSections\SiteSectionResource;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Forms\CmsFormEndpoint;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SectionLibrary;
use Vortechron\FilamentCms\SiteInfo;
use Vortechron\FilamentCms\SitePageRenderer;

/**
 * The page builder, full screen, for one document: a page, the header or
 * footer, a synced or saved section, an entry's body, or an entry type's
 * card or single page.
 * /admin/site-pages/{id}/build; "header" and "footer" in place of the id
 * open the header and footer, made on first use.
 *
 * Anyone who may edit pages may build any of these kinds. Locks and
 * developer-only data are kept by the store's guard.
 *
 * @property Document $record
 */
class BuildSitePage extends Page
{
    use InteractsWithPageBuilder {
        getPageBuilderOptions as private pageBuilderBaseOptions;
        getPageBuilderSupports as private pageBuilderBaseSupports;
        savePageBuilder as private pageBuilderBaseSave;
    }
    use InteractsWithRecord;

    protected static string $resource = SitePageResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizePageBuilder();
    }

    protected function resolveRecord(int|string $key): Model
    {
        if (CmsKind::tryFrom((string) $key)?->isPart() === true) {
            // Check before the part is made, not after.
            abort_unless(self::mayBuild(null), 403);

            return Document::findOrCreatePart((string) $key);
        }

        $kind = CmsKind::tryFrom((string) Document::query()->whereKey($key)->value('kind'));
        abort_if($kind?->builderKind() === null, 404);

        $document = $kind->model()::query()->findOrFail($key);
        abort_unless(EntryBuilder::buildable($document), 404);

        return $document;
    }

    /**
     * Only someone who may edit pages may open or use the builder: for a
     * page, that page; for an entry, that entry (its Entry policy); for
     * every other kind, pages in general.
     */
    protected function authorizePageBuilder(): void
    {
        abort_unless(self::mayBuild($this->record), 403);
    }

    public static function mayBuild(?Document $document): bool
    {
        // The screen the document belongs to: entries and their designs, sections, or pages.
        $kind = $document?->cmsKind();
        $screen = match (true) {
            $kind?->belongsToEntryType() === true => 'entries',
            $kind?->holdsOneSection() === true => 'sections',
            default => 'pages',
        };

        if (! CmsAccess::allows($screen)) {
            return false;
        }

        if ($document instanceof Entry) {
            return EntryResource::canEdit($document);
        }

        return SitePageResource::canEdit($document instanceof SitePage ? $document : new SitePage);
    }

    public function getTitle(): string
    {
        return $this->record->cmsKind()?->isPart() === true ? $this->record->cmsKind()->label() : (string) $this->record->title;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPageBuilderOptions(): array
    {
        $this->authorizePageBuilder();

        $options = $this->pageBuilderBaseOptions();

        if ($this->plugin()->hasSections()) {
            $options['templates'] = [...$options['templates'] ?? [], ...app(SectionLibrary::class)->templates()];
        }

        if (! $this->plugin()->hasEntries()) {
            $options['blocks'] = array_values(array_filter($options['blocks'] ?? [], fn (array $block): bool => $block['kind'] !== EntryList::KIND));
        }

        return $options;
    }

    /**
     * @return array{saveKit: bool, saveSection: bool, saveSiteInfo: bool}
     */
    public function getPageBuilderSupports(): array
    {
        $this->authorizePageBuilder();

        return [
            ...$this->pageBuilderBaseSupports(),
            ...($this->plugin()->hasSections() ? [] : ['saveSection' => false]),
            ...($this->plugin()->hasParts() ? [] : ['saveSiteInfo' => false]),
        ];
    }

    /**
     * The trait's save, with a value the store refuses returned as errors.
     *
     * @param  array<string, mixed>  $value
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function savePageBuilder(array $value): array
    {
        try {
            return $this->pageBuilderBaseSave($value);
        } catch (ValidationException $exception) {
            return ['ok' => false, 'errors' => array_map(fn (array $messages): string => (string) ($messages[0] ?? ''), $exception->errors())];
        }
    }

    protected function getPageBuilderValue(): array
    {
        return is_array($this->record->content) ? $this->record->content : [];
    }

    protected function savePageBuilderValue(array $value): void
    {
        $this->saveThroughStore($this->record, $value);
    }

    protected function renderPageBuilderPreview(array $sections): string
    {
        $renderer = app(SitePageRenderer::class);

        if (EntryBuilder::type($this->record) !== null) {
            return EntryBuilder::preview($this->record, $sections);
        }

        return ($this->record instanceof SitePage
            ? $renderer->view($this->record, $sections, forBuilder: true)
            : $renderer->preview($this->record, $sections))->render();
    }

    protected function getPageBuilderBackUrl(): ?string
    {
        if (($entries = EntryBuilder::backUrl($this->record)) !== null) {
            return $entries;
        }

        return $this->record->cmsKind()?->holdsOneSection() === true && $this->plugin()->hasSections()
            ? SiteSectionResource::getUrl()
            : SitePageResource::getUrl();
    }

    protected function getPageBuilderViewUrl(): ?string
    {
        return $this->record->cmsKind()?->isRoutable() === true && $this->record->is_published ? $this->record->url() : null;
    }

    /**
     * The builder's kind for it (CmsKind::builderKind()): an entry's body is
     * built as a page, a saved section as one section.
     */
    protected function getPageBuilderKind(): string
    {
        return ($this->record->cmsKind()?->builderKind() ?? DocumentKind::Page)->value;
    }

    /**
     * The entry.* values an entry, card or single offers.
     */
    protected function getPageBuilderDynamicValues(): array
    {
        return EntryBuilder::dynamicValues($this->record);
    }

    /**
     * The page.* values of the page it is shown on (the page itself, an
     * entry's own page, else the home page) and the entry.* values of the
     * entry it shows, so the builder's chips and preview show real values.
     */
    protected function getPageBuilderRenderValues(): array
    {
        $page = $this->record instanceof SitePage ? $this->record : EntryBuilder::page($this->record);
        $pageValues = $page === null ? SitePageRenderer::homeValues() : SitePageRenderer::pageValues($page);

        return Arr::except([...$pageValues, ...EntryBuilder::entryValues($this->record)], CmsFormEndpoint::VALUE);
    }

    protected function isPageBuilderDeveloper(): bool
    {
        return $this->plugin()->isDeveloper(Filament::auth()->user());
    }

    protected function getPageBuilderKit(): ?array
    {
        return app(KitSource::class)->current();
    }

    protected function savePageBuilderKit(array $kit): void
    {
        $this->saveThroughStore(Document::firstOfKind(Document::KIT) ?? new Document(['kind' => Document::KIT, 'title' => CmsKind::Kit->label()]), $kit);
    }

    protected function getPageBuilderParts(): array
    {
        if (! $this->plugin()->hasParts()) {
            return [];
        }

        return array_map(fn (CmsKind $kind): array => [
            'key' => $kind->value,
            'title' => $kind->label(),
            'editUrl' => static::getUrl(['record' => $kind->value]),
        ], CmsKind::parts());
    }

    /**
     * Every page, drafts too, for the menu under a page's title: the home
     * page first, then the others in menu order, each a link to its own
     * builder. Only a page's builder has it.
     *
     * @return list<array{title: string, url: string, current: bool}>
     */
    protected function getPageBuilderPageMenu(): array
    {
        if ($this->record->cmsKind() !== CmsKind::Page) {
            return [];
        }

        return SitePage::query()
            ->orderByRaw("path = '/' desc")
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'title'])
            ->map(fn (SitePage $page): array => [
                'title' => (string) $page->title,
                'url' => static::getUrl(['record' => $page->getKey()]),
                'current' => (string) $page->getKey() === (string) $this->record->getKey(),
            ])
            ->all();
    }

    /**
     * The published pages a link can point to: the home page first, then
     * the others in menu order (sort, then the order they were made).
     *
     * @return list<array{title: string, url: string}>
     */
    protected function getPageBuilderPages(): array
    {
        return SitePage::query()
            ->published()
            ->orderByRaw("path = '/' desc")
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['title', 'path'])
            ->map(fn (SitePage $page): array => ['title' => (string) $page->title, 'url' => $page->path])
            ->all();
    }

    protected function getPageBuilderSiteInfo(): ?array
    {
        return $this->plugin()->hasParts() ? app(SiteInfo::class)->forBuilder() : null;
    }

    /**
     * The preview drawn with the shop info the builder has not saved yet:
     * cleaned like a save (a value that fails the checks shows the stored
     * one) and read by every settings reader for this draw only. Nothing
     * is saved.
     */
    protected function withPageBuilderSiteInfo(array $values, Closure $draw): string
    {
        if (! $this->plugin()->hasParts()) {
            return $draw();
        }

        return app(Cms::class)->withSettings(app(SiteInfo::class)->previewSettings($values), $draw);
    }

    protected function savePageBuilderSiteInfo(array $values): void
    {
        if (! $this->plugin()->hasParts()) {
            throw ValidationException::withMessages(['siteInfo' => __('filament-cms::documents.parts_off')]);
        }

        app(SiteInfo::class)->save($values, Filament::auth()->user());
    }

    protected function savePageBuilderSection(array $section, string $title, bool $synced): array
    {
        if (! $this->plugin()->hasSections()) {
            return ['ok' => false, 'errors' => ['section' => __('filament-cms::sections.off')]];
        }

        return app(SectionLibrary::class)->save($section, $title, $synced, Filament::auth()->user(), $this->isPageBuilderDeveloper());
    }

    protected function getPageBuilderSyncedSections(): array
    {
        if (! $this->plugin()->hasSections()) {
            return [];
        }

        return app(SectionLibrary::class)->synced()->map(fn (Document $synced): array => [
            'id' => $synced->getKey(),
            'title' => (string) $synced->title,
            'editUrl' => static::getUrl(['record' => $synced]),
            'section' => SectionLibrary::firstSection($synced),
        ])->all();
    }

    /**
     * @param  array<mixed>  $value
     *
     * @throws ValidationException when the store refuses the value
     */
    private function saveThroughStore(Document $document, array $value): void
    {
        $result = app(DocumentStore::class)->save($document, $value, Filament::auth()->user(), $this->isPageBuilderDeveloper());

        if (! $result['ok']) {
            throw ValidationException::withMessages($result['errors'] ?? []);
        }
    }

    private function plugin(): FilamentCmsPlugin
    {
        return FilamentCmsPlugin::get();
    }
}
