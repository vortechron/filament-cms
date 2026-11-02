<?php

namespace Vortechron\FilamentCms\Filament\Pages;

use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;
use Vortechron\FilamentCms\Cms;

/**
 * The site-wide settings: /admin/settings.
 */
class EditSettings extends EditContent
{
    protected static ?string $slug = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make(app(Cms::class)->definition(Cms::SETTINGS)::label())
                ->icon(static::$navigationIcon)
                ->sort(100)
                ->url(static::getUrl())
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteName())),
        ];
    }

    protected function keyFromRequest(): string
    {
        return Cms::SETTINGS;
    }

    protected function canEdit(string $key): bool
    {
        return $key === Cms::SETTINGS && app(Cms::class)->has($key);
    }
}
