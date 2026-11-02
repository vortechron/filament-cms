<?php

namespace Vortechron\FilamentCms\Filament\Resources\SitePages\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Vortechron\FilamentBlockEditor\Filament\Concerns\InteractsWithPageBuilder;
use Vortechron\FilamentCms\Filament\Resources\SitePages\SitePageResource;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SitePageRenderer;
use Vortechron\FilamentCms\SitePageStore;

/**
 * The page builder, full screen, for one site page.
 *
 * @property SitePage $record
 */
class BuildSitePage extends Page
{
    use InteractsWithPageBuilder;
    use InteractsWithRecord;

    protected static string $resource = SitePageResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizePageBuilder();
    }

    /**
     * Only someone who may edit this page may open or use its builder.
     */
    protected function authorizePageBuilder(): void
    {
        abort_unless(SitePageResource::canEdit($this->record), 403);
    }

    public function getTitle(): string
    {
        return $this->record->title;
    }

    protected function getPageBuilderValue(): array
    {
        return ['sections' => $this->record->sections ?? []];
    }

    protected function savePageBuilderValue(array $value): void
    {
        app(SitePageStore::class)->save($this->record, $value['sections'] ?? [], Filament::auth()->user());
    }

    protected function renderPageBuilderPreview(array $sections): string
    {
        return app(SitePageRenderer::class)->view($this->record, $sections, forBuilder: true)->render();
    }

    protected function getPageBuilderBackUrl(): ?string
    {
        return SitePageResource::getUrl();
    }

    protected function getPageBuilderViewUrl(): ?string
    {
        return $this->record->is_published ? $this->record->url() : null;
    }
}
