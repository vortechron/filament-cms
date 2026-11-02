<?php

namespace Vortechron\FilamentCms;

use Illuminate\Support\Facades\Route;
use Vortechron\FilamentCms\Http\Controllers\ShowSitePage;

final class FilamentCms
{
    /**
     * Shows site pages at their own addresses. Call it last in
     * routes/web.php: it answers every address no other route took.
     */
    public static function routes(): void
    {
        Route::get('/{path?}', ShowSitePage::class)
            ->where('path', '(?!(?:up|livewire|admin|storage|filament|css|js|fonts|vendor)(?:/|$)).*')
            ->name('filament-cms.page');
    }
}
