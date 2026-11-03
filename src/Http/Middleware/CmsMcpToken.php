<?php

namespace Vortechron\FilamentCms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request reach the site's MCP server only with the secret from
 * `config('filament-cms.mcp.token')` (env CMS_MCP_TOKEN), sent as
 * `Authorization: Bearer <token>`. With no token configured the address
 * does not exist (404); a missing or wrong token is refused (401). The
 * token is compared in constant time and never logged or returned.
 */
final class CmsMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('filament-cms.mcp.token');

        if (! is_string($token) || trim($token) === '') {
            abort(404);
        }

        $given = $request->bearerToken();

        if (! is_string($given) || ! hash_equals($token, $given)) {
            return response()->json(['message' => 'A valid MCP token is required.'], 401, ['WWW-Authenticate' => 'Bearer realm="mcp"']);
        }

        return $next($request);
    }
}
