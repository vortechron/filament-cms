<?php

namespace Vortechron\FilamentCms;

use Illuminate\Contracts\View\View;
use Vortechron\FilamentBlockEditor\Rendering\PageRenderer;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Models\SitePage;

/**
 * Draws a site page inside the project's own page view
 * (config filament-cms.page_view), for visitors and for the builder's
 * live preview.
 */
final class SitePageRenderer
{
    /**
     * @param  list<array<string, mixed>>|null  $sections  unsaved sections for a preview
     */
    public function view(SitePage $page, ?array $sections = null, bool $forBuilder = false): View
    {
        $context = new RenderContext(forBuilder: $forBuilder, isPreview: $forBuilder, values: self::pageValues($page));
        $sections ??= $page->sections ?? [];

        return view(config('filament-cms.page_view', 'cms.page'), [
            'page' => $page,
            'sections' => $sections,
            'context' => $context,
            'content' => app(PageRenderer::class)->render($sections, $context),
            'isBuilderPreview' => $forBuilder,
        ]);
    }

    /**
     * The page.* dynamic values of a page.
     *
     * @return array<string, string>
     */
    public static function pageValues(SitePage $page): array
    {
        return ['page.title' => (string) $page->title, 'page.url' => $page->url()];
    }
}
