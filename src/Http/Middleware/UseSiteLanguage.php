<?php

namespace Vortechron\FilamentCms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vortechron\FilamentBlockEditor\Rendering\ContentLocale;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\SiteLanguages;

/**
 * Answers a visitor in the site's language (SITE_LOCALE), not the panel's:
 * the page and a form's errors. An error page drawn after the request ends
 * (a 404) is in the app's language. The app gets its own
 * language back afterwards, and ContentLocale::panel() still names it, so
 * the shop's email goes out in the panel's language.
 */
final class UseSiteLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SiteLanguages::enabled()) {
            return ContentLocale::run(fn (): Response => $next($request));
        }

        $document = $request->route('document');
        $locale = is_string($document) && ctype_digit($document)
            ? (Document::find((int) $document)?->language() ?? SiteLanguages::default())
            : SiteLanguages::forPath($request->path());
        abort_unless(isset(SiteLanguages::labels()[$locale]), 404);

        return SiteLanguages::run($locale, fn (): Response => $next($request));
    }
}
