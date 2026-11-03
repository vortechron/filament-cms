<?php

namespace Vortechron\FilamentCms\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who the MCP server saves as. It always counts as a developer; its saved
 * versions are kept under the user whose email is
 * `config('filament-cms.mcp.user')`, or under no user.
 */
final class McpActor
{
    public static function user(): ?Authenticatable
    {
        $email = config('filament-cms.mcp.user');

        /** @var class-string<Model>|null $model */
        $model = config('auth.providers.users.model');

        if (! is_string($email) || trim($email) === '' || ! is_string($model) || ! class_exists($model)) {
            return null;
        }

        $user = $model::query()->where('email', trim($email))->first();

        return $user instanceof Authenticatable ? $user : null;
    }
}
