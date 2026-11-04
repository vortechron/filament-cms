<?php

namespace Vortechron\FilamentCms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vortechron\FilamentBlockEditor\Rendering\ContentLocale;

/**
 * Answers a visitor in the site's language (SITE_LOCALE), not the panel's:
 * the page, a form's errors and the not-found page. The app gets its own
 * language back afterwards, and ContentLocale::panel() still names it, so
 * the shop's email goes out in the panel's language.
 */
final class UseSiteLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        return ContentLocale::run(fn (): Response => $next($request));
    }
}
