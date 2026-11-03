<?php

namespace Vortechron\FilamentCms\Http\Controllers;

use Illuminate\Contracts\View\View;
use Vortechron\FilamentCms\Entries\EntryPageRenderer;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SitePageRenderer;

/**
 * Serves every published document with an address: a site page, or an
 * entry whose type is registered and has pages (EntryType::path()).
 */
class ShowSitePage
{
    public function __invoke(?string $path = null): View
    {
        $path = SitePage::cleanPath($path);
        $page = SitePage::query()->published()->where('path', $path)->first();

        if ($page !== null) {
            return app(SitePageRenderer::class)->view($page);
        }

        $entry = Entry::query()->published()->where('path', $path)->first();
        abort_if($entry?->definition()?->getPath() === null, 404);

        return app(EntryPageRenderer::class)->view($entry);
    }
}
