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
     * The roles the signed-in person may give, and so the people they may
     * see and change, by name, with their labels: every role for a super
     * admin; for anyone else, never super admin, and only a role whose
     * screens they open themselves, so nobody hands out or takes over more
     * than they have.
     *
     * @return array<string, string>
     */
    public static function assignableRoles(): array
    {
        $user = Filament::auth()->user();
        $super = self::isSuperAdmin($user);
        $own = $user !== null && method_exists($user, 'getAllPermissions') ? $user->getAllPermissions()->pluck('name') : collect();

        return Role::query()
            ->with('permissions')
            ->where('guard_name', CmsRoles::guard())
            ->orderBy('id')
            ->get()
            ->filter(fn (Role $role): bool => $super
                || ($role->name !== CmsRoles::SUPER_ADMIN && $role->permissions->pluck('name')->diff($own)->isEmpty()))
            ->mapWithKeys(fn (Role $role): array => [$role->name => CmsRoles::roleLabel($role->name)])
            ->all();
    }
}
