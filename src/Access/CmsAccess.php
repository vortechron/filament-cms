<?php

namespace Vortechron\FilamentCms\Access;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Role;
use Vortechron\FilamentCms\FilamentCmsPlugin;

/**
 * Who may open which CMS screen. With roles on (the plugin's ->roles()),
 * the screen's permission decides ("cms.pages"); with roles off, everyone
 * signed in opens the content screens, only a developer opens Content
 * types, and Users and Roles stay closed.
 */
final class CmsAccess
{
    public static function allows(string $screen): bool
    {
        $plugin = FilamentCmsPlugin::get();
        $user = Filament::auth()->user();

        if ($user === null) {
            return false;
        }

        if (! $plugin->hasRoles()) {
            return match ($screen) {
                'users', 'roles' => false,
                'types' => $plugin->isDeveloper($user),
                default => true,
            };
        }

        return $user->can(CmsRoles::permission($screen));
    }

    public static function isSuperAdmin(?Authenticatable $user = null): bool
    {
        $user ??= Filament::auth()->user();

        return $user !== null && method_exists($user, 'hasRole') && $user->hasRole(CmsRoles::SUPER_ADMIN);
    }

    /**
     * The roles the signed-in person may give, by name, with their labels:
     * every role for a super admin, all but super admin for anyone else.
     *
     * @return array<string, string>
     */
    public static function assignableRoles(): array
    {
        $super = self::isSuperAdmin();

        return Role::query()
            ->where('guard_name', CmsRoles::guard())
            ->orderBy('id')
            ->pluck('name')
            ->reject(fn (string $name): bool => ! $super && $name === CmsRoles::SUPER_ADMIN)
            ->mapWithKeys(fn (string $name): array => [$name => CmsRoles::roleLabel($name)])
            ->all();
    }
}
