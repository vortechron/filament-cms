<?php

namespace Vortechron\FilamentCms;

use Illuminate\Support\Facades\Route;
use Vortechron\FilamentCms\Http\Controllers\ShowSitePage;
use Vortechron\FilamentCms\Http\Controllers\SubmitForm;

final class FilamentCms
{
    /**
     * Shows site pages at their own addresses. Call it last in
     * routes/web.php: it answers every address no other route took.
     */
    public static function routes(): void
    {
        // A visitor sends a form block; the stored form decides what is kept.
        Route::post('/_cms/forms/{document}', SubmitForm::class)->name('filament-cms.forms.submit');

        Route::get('/{path?}', ShowSitePage::class)
            ->where('path', '(?!(?:up|livewire|admin|storage|filament|css|js|fonts|vendor)(?:/|$)).*')
            ->name('filament-cms.page');
    }
}
