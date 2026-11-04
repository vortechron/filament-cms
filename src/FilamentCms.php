<?php

namespace Vortechron\FilamentCms;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Vortechron\FilamentCms\Forms\CmsFormEndpoint;
use Vortechron\FilamentCms\Http\Controllers\ShowSitePage;
use Vortechron\FilamentCms\Http\Controllers\SubmitForm;
use Vortechron\FilamentCms\Http\Middleware\CmsMcpToken;
use Vortechron\FilamentCms\Http\Middleware\UseSiteLanguage;
use Vortechron\FilamentCms\Mcp\CmsServer;

final class FilamentCms
{
    /**
     * Calls to the MCP server allowed per minute, from one address.
     */
    public const MCP_PER_MINUTE = 60;

    /**
     * First parts of an address the site or the CMS itself answers, so the
     * page route leaves them alone and no entry type may use them:
     * Laravel's health check, Livewire, the panel, files and built assets,
     * the MCP server, and the form route (`/_cms/forms/{document}`).
     */
    public const RESERVED_PATHS = ['up', 'livewire', 'admin', 'storage', 'filament', 'css', 'js', 'fonts', 'vendor', 'mcp', '_cms'];

    /**
     * Shows site pages at their own addresses. Call it last in
     * routes/web.php: it answers every address no other route took.
     * Both answer in the site's language (Http\Middleware\UseSiteLanguage).
     */
    public static function routes(): void
    {
        Route::middleware(UseSiteLanguage::class)->group(function (): void {
            // A visitor sends a form block; the stored form decides what is kept.
            Route::post('/_cms/forms/{document}', SubmitForm::class)->name(CmsFormEndpoint::ROUTE);

            $reserved = implode('|', array_map(fn (string $path): string => preg_quote($path, '/'), self::RESERVED_PATHS));

            Route::get('/{path?}', ShowSitePage::class)
                ->where('path', "(?!(?:{$reserved})(?:/|$)).*")
                ->name('filament-cms.page');
        });
    }

    /**
     * The site's MCP server, so an AI agent can read and edit the content
     * through the same checks as the panel. It needs the token from
     * `config('filament-cms.mcp.token')` (env CMS_MCP_TOKEN); with none set
     * the address answers 404. It is an API: no CSRF token, and at most
     * MCP_PER_MINUTE calls a minute. Call it before routes().
     */
    public static function mcp(string $path = '/mcp'): void
    {
        Route::withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class, VerifyCsrfToken::class])
            ->middleware([ThrottleRequests::with(self::MCP_PER_MINUTE, 1, 'filament-cms-mcp'), CmsMcpToken::class])
            ->group(fn () => Mcp::web($path, CmsServer::class)->name('filament-cms.mcp'));
    }
}
