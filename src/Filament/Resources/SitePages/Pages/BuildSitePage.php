<?php

namespace Vortechron\FilamentCms\Filament\Resources\SitePages\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Vortechron\FilamentBlockEditor\Design\KitSource;
use Vortechron\FilamentBlockEditor\Filament\Concerns\InteractsWithPageBuilder;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Filament\Resources\SitePages\SitePageResource;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SectionLibrary;
use Vortechron\FilamentCms\SiteInfo;
use Vortechron\FilamentCms\SitePageRenderer;

/**
 * The page builder, full screen, for one document: a page, the header or
 * footer, a synced section, or an entry type's card or single page.
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

    /**
     * The kinds this page builds.
     */
    public const KINDS = [Document::PAGE, Document::HEADER, Document::FOOTER, Document::SECTION, Document::CARD, Document::SINGLE];

    protected static string $resource = SitePageResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizePageBuilder();
    }

    protected function resolveRecord(int|string $key): Model
    {
        if (in_array($key, Document::PARTS, true)) {
            // Check before the part is made, not after.
            abort_unless(self::mayBuild(null), 403);

            return Document::findOrCreatePart((string) $key);
        }

        return SitePage::query()->find($key)
            ?? Document::query()->whereIn('kind', self::KINDS)->whereKey($key)->firstOrFail();
    }

    /**
     * Only someone who may edit pages may open or use the builder: for a
     * page, that page; for every other kind, pages in general.
     */
    protected function authorizePageBuilder(): void
    {
        abort_unless(self::mayBuild($this->record), 403);
    }

    public static function mayBuild(?Document $document): bool
    {
        return SitePageResource::canEdit($document instanceof SitePage ? $document : new SitePage);
    }

    public function getTitle(): string
    {
        return in_array($this->record->kind, Document::PARTS, true)
            ? __("filament-cms::documents.{$this->record->kind}")
            : (string) $this->record->title;
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

        return ($this->record instanceof SitePage
            ? $renderer->view($this->record, $sections, forBuilder: true)
            : $renderer->preview($this->record, $sections))->render();
    }

    protected function getPageBuilderBackUrl(): ?string
    {
        return SitePageResource::getUrl();
    }

    protected function getPageBuilderViewUrl(): ?string
    {
        return $this->record instanceof SitePage && $this->record->is_published ? $this->record->url() : null;
    }

    protected function getPageBuilderKind(): string
    {
        return $this->record->kind;
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
        $this->saveThroughStore(Document::firstOfKind(Document::KIT) ?? new Document(['kind' => Document::KIT, 'title' => 'Kit']), $kit);
    }

    protected function getPageBuilderParts(): array
    {
        if (! $this->plugin()->hasParts()) {
            return [];
        }

        return array_map(fn (string $kind): array => [
            'key' => $kind,
            'title' => __("filament-cms::documents.{$kind}"),
            'editUrl' => static::getUrl(['record' => $kind]),
        ], Document::PARTS);
    }

    protected function getPageBuilderSiteInfo(): ?array
    {
        return $this->plugin()->hasParts() ? app(SiteInfo::class)->forBuilder() : null;
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
