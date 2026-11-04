<?php

namespace Vortechron\FilamentCms\Access;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The CMS's roles and permissions (spatie/laravel-permission), on when the
 * panel's plugin has ->roles(). One permission per screen ("cms.pages"),
 * not per button.
 *
 * `super_admin` (Vortechron) always holds every CMS permission and counts
 * as a developer. `admin` (the client) starts with all but Roles and
 * Content types. Other roles ("staff") are made on the Roles screen.
 */
final class CmsRoles
{
    public const SUPER_ADMIN = 'super_admin';

    public const ADMIN = 'admin';

    /**
     * Every CMS screen's permission, in the order the Roles screen lists them.
     */
    public const PERMISSIONS = ['cms.pages', 'cms.sections', 'cms.forms', 'cms.entries', 'cms.settings', 'cms.users', 'cms.roles', 'cms.types'];

    /**
     * The permissions a new admin role does not get.
     */
    public const SUPER_ADMIN_ONLY = ['cms.roles', 'cms.types'];

    /**
     * The roles the Roles screen never deletes or renames.
     */
    public const BUILT_IN = [self::SUPER_ADMIN, self::ADMIN];

    public static function permission(string $screen): string
    {
        return 'cms.'.$screen;
    }

    public static function guard(): string
    {
        return (string) config('auth.defaults.guard', 'web');
    }

    /**
     * Makes what is missing, and changes nothing the owner set: the CMS
     * permissions, the super admin role with every one of them, and the admin
     * role. A permission new in this release also goes to admin, unless it
     * is a super admin one. Safe to run any number of times.
     */
    public static function sync(): void
    {
        $guard = self::guard();
        $existing = Permission::query()->where('guard_name', $guard)->pluck('name')->all();
        $new = array_values(array_diff(self::PERMISSIONS, $existing));

        foreach ($new as $name) {
            Permission::query()->create(['name' => $name, 'guard_name' => $guard]);
        }

        $admin = Role::query()->where('name', self::ADMIN)->where('guard_name', $guard)->first();
        $forAdmin = $admin === null ? self::PERMISSIONS : $new;
        $admin ??= Role::query()->create(['name' => self::ADMIN, 'guard_name' => $guard]);
        $super = Role::findOrCreate(self::SUPER_ADMIN, $guard);

        self::forget();

        $super->givePermissionTo(array_values(array_diff(self::PERMISSIONS, $super->permissions()->pluck('name')->all())));
        $admin->givePermissionTo(array_values(array_diff($forAdmin, self::SUPER_ADMIN_ONLY)));

        self::forget();
    }

    /**
     * Whether a CMS permission is missing, so sync() has work to do. Reads
     * the permissions Spatie keeps in the cache.
     */
    public static function needsSync(): bool
    {
        $names = app(PermissionRegistrar::class)->getPermissions(['guard_name' => self::guard()])->pluck('name')->all();

        return array_diff(self::PERMISSIONS, $names) !== [];
    }

    public static function forget(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function roleLabel(string $name): string
    {
        $labels = (array) __('filament-cms::access.role_labels');

        return is_string($labels[$name] ?? null) ? $labels[$name] : Str::headline($name);
    }

    public static function permissionLabel(string $name): string
    {
        $labels = (array) __('filament-cms::access.permission_labels');

        return is_string($labels[$name] ?? null) ? $labels[$name] : $name;
    }
}
