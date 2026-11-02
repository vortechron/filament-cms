<?php

namespace Vortechron\FilamentCms;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Vortechron\FilamentCms\Filament\Pages\EditContent;
use Vortechron\FilamentCms\Filament\Pages\EditSettings;

/**
 * Add to a panel to let the client edit the pages in config/filament-cms.php:
 *
 *     ->plugin(FilamentCmsPlugin::make()->developers(fn (User $user) => $user->is_developer))
 */
class FilamentCmsPlugin implements Plugin
{
    public const ID = 'filament-cms';

    protected ?Closure $isDeveloperUsing = null;

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
     * Who counts as a developer. Only developers may restore old versions.
     */
    public function developers(Closure $callback): static
    {
        $this->isDeveloperUsing = $callback;

        return $this;
    }

    public function isDeveloper(?Authenticatable $user): bool
    {
        return $user !== null && $this->isDeveloperUsing !== null && (bool) ($this->isDeveloperUsing)($user);
    }

    public function register(Panel $panel): void
    {
        $panel->pages([EditContent::class, EditSettings::class]);
    }

    public function boot(Panel $panel): void {}
}
