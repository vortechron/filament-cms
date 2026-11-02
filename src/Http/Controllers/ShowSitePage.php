<?php

namespace Vortechron\FilamentCms\Http\Controllers;

use Illuminate\Contracts\View\View;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SitePageRenderer;

class ShowSitePage
{
    public function __invoke(?string $path = null): View
    {
        $page = SitePage::query()->published()->where('path', SitePage::cleanPath($path))->firstOrFail();

        return app(SitePageRenderer::class)->view($page);
    }
}
