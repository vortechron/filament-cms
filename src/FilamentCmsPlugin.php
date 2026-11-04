<?php

namespace Vortechron\FilamentCms;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Vortechron\FilamentCms\Access\CmsRoles;
use Vortechron\FilamentCms\Filament\Pages\EditContent;
use Vortechron\FilamentCms\Filament\Pages\EditContentTypes;
use Vortechron\FilamentCms\Filament\Pages\EditSettings;
use Vortechron\FilamentCms\Filament\Resources\Entries\EntryResource;
use Vortechron\FilamentCms\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Vortechron\FilamentCms\Filament\Resources\Roles\RoleResource;
use Vortechron\FilamentCms\Filament\Resources\SitePages\SitePageResource;
use Vortechron\FilamentCms\Filament\Resources\SiteSections\SiteSectionResource;
use Vortechron\FilamentCms\Filament\Resources\Users\UserResource;

/**
 * Add to a panel to let the client edit the pages in config/filament-cms.php:
 *
 *     ->plugin(FilamentCmsPlugin::make()->roles())
 *
 * With ->roles(), each screen needs its permission (Access\CmsRoles) and
 * the super admin role counts as a developer. Without it, every signed-in
 * user opens the content screens and ->developers() names the developers.
 */
class FilamentCmsPlugin implements Plugin
{
    public const ID = 'filament-cms';

    protected ?Closure $isDeveloperUsing = null;

    protected bool $hasSitePages = true;

    protected bool $hasParts = true;

    protected bool $hasSections = true;

    protected bool $hasForms = true;

    protected bool $hasEntries = true;

    protected bool $hasRoles = false;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(self::ID);
    }

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * Who counts as a developer, besides the super admin role when roles
     * are on. Only developers may restore old versions, set locks and
     * custom CSS in the page builder, and change what is locked.
     */
    public function developers(Closure $callback): static
    {
        $this->isDeveloperUsing = $callback;

        return $this;
    }

    /**
     * Turn on roles and permissions (spatie/laravel-permission): the Users
     * and Roles screens, one permission per screen, and the super admin role
     * as the developer. The user model needs Spatie's HasRoles trait and the
     * app Spatie's tables. Off by default.
     */
    public function roles(bool $condition = true): static
    {
        $this->hasRoles = $condition;

        return $this;
    }

    public function hasRoles(): bool
    {
        return $this->hasRoles;
    }

    /**
     * Show the Halaman screen, where the client builds pages in the page
     * builder. On by default.
     */
    public function sitePages(bool $condition = true): static
    {
        $this->hasSitePages = $condition;

        return $this;
    }

    /**
     * The header and footer: built like a page, drawn around every page. On
     * by default.
     */
    public function parts(bool $condition = true): static
    {
        $this->hasParts = $condition;

        return $this;
    }

    public function hasParts(): bool
    {
        return $this->hasParts;
    }

    /**
     * Synced and saved sections, and the Seksyen screen that lists them. On
     * by default.
     */
    public function sections(bool $condition = true): static
    {
        $this->hasSections = $condition;

        return $this;
    }

    public function hasSections(): bool
    {
        return $this->hasSections;
    }

    /**
     * The "Borang" screen: what visitors sent with the site's form blocks.
     * On by default. Forms still save submissions when it is off.
     */
    public function forms(bool $condition = true): static
    {
        $this->hasForms = $condition;

        return $this;
    }

    /**
     * One screen per entry type under "Kandungan" (see EntryTypes). On by
     * default. The builder parts (Bina, the card and entry page designs)
     * also need sitePages().
     */
    public function entries(bool $condition = true): static
    {
        $this->hasEntries = $condition;

        return $this;
    }

    public function hasForms(): bool
    {
        return $this->hasForms;
    }

    public function hasEntries(): bool
    {
        return $this->hasEntries;
    }

    public function hasSitePages(): bool
    {
        return $this->hasSitePages;
    }

    public function isDeveloper(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isDeveloperUsing !== null && (bool) ($this->isDeveloperUsing)($user)) {
            return true;
        }

        return $this->hasRoles && method_exists($user, 'hasRole') && $user->hasRole(CmsRoles::SUPER_ADMIN);
    }

    public function register(Panel $panel): void
    {
        $panel->pages([EditContent::class, EditSettings::class]);

        if ($this->hasSitePages) {
            $panel->resources([SitePageResource::class]);
        }

        if ($this->hasSitePages && $this->hasSections) {
            $panel->resources([SiteSectionResource::class]);
        }

        if ($this->hasForms) {
            $panel->resources([FormSubmissionResource::class]);
        }

        if ($this->hasEntries) {
            $panel->resources([EntryResource::class]);
            $panel->pages([EditContentTypes::class]);
        }

        // Always there, so ->roles() can be switched on later; closed while roles are off.
        $panel->resources([UserResource::class, RoleResource::class]);
    }

    public function boot(Panel $panel): void
    {
        // A release with a new screen brings its permission on the next visit.
        if ($this->hasRoles && CmsRoles::needsSync()) {
            CmsRoles::sync();
        }
    }
}
