<?php

namespace Vortechron\FilamentCms\Filament\Resources\Entries;

use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Vortechron\FilamentCms\Access\CmsAccess;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Filament\Resources\Entries\Pages\ManageEntries;
use Vortechron\FilamentCms\Models\Entry;

use function Filament\Support\original_request;

/**
 * The entries of every entry type: one screen per type under "Kandungan",
 * /admin/entries/{type}. The type's fields make the form (EntryForm). An
 * Entry policy decides who may open and change them.
 */
class EntryResource extends Resource
{
    protected static ?string $model = Entry::class;

    protected static ?string $slug = 'entries';

    protected static ?int $navigationSort = -8;

    public static function canAccess(): bool
    {
        return parent::canAccess() && CmsAccess::allows('entries');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-cms::cms.content');
    }

    public static function getModelLabel(): string
    {
        return __('filament-cms::entries.entry');
    }

    /**
     * One item per registered type.
     *
     * @return list<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        return array_values(array_map(fn (EntryType $type): NavigationItem => NavigationItem::make($type->getPluralLabel())
            ->key(static::class.'.'.$type->key)
            ->group(static::getNavigationGroup())
            ->icon($type->getIcon() ?? Heroicon::OutlinedRectangleStack)
            ->isActiveWhen(fn (): bool => original_request()->routeIs(static::getRouteBaseName().'.*') && original_request()->route('type') === $type->key)
            ->sort(static::getNavigationSort())
            ->url(static::getUrl('index', ['type' => $type->key])), app(EntryTypes::class)->all()));
    }

    public static function getPages(): array
    {
        return ['index' => ManageEntries::route('/{type}')];
    }
}
