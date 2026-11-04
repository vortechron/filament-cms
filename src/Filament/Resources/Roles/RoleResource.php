<?php

namespace Vortechron\FilamentCms\Filament\Resources\Roles;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Vortechron\FilamentCms\Access\CmsAccess;
use Vortechron\FilamentCms\Access\CmsRoles;
use Vortechron\FilamentCms\Filament\Resources\Roles\Pages\ManageRoles;

/**
 * The roles and the screens each one opens. Only someone with "cms.roles"
 * (the super admin) opens it. Super admin always opens every screen, so it
 * is never edited; super admin and admin are never deleted or renamed.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'roles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 91;

    public static function canAccess(): bool
    {
        return CmsAccess::allows('roles');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-cms::access.group');
    }

    public static function getModelLabel(): string
    {
        return __('filament-cms::access.role_model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-cms::access.roles');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('guard_name', CmsRoles::guard())->withCount(['permissions', 'users']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('filament-cms::access.role_name'))
                    ->helperText(__('filament-cms::access.role_name_help'))
                    ->required()->maxLength(40)->regex('/^[a-z][a-z0-9_]*$/')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('guard_name', CmsRoles::guard()))
                    ->disabled(fn (?Model $record): bool => self::isBuiltIn($record))
                    ->dehydrated(fn (?Model $record): bool => ! self::isBuiltIn($record)),
                TextInput::make('guard_name')->default(CmsRoles::guard())->hidden()->dehydratedWhenHidden(),
                CheckboxList::make('permissions')->label(__('filament-cms::access.permissions'))
                    ->relationship('permissions', 'name', fn (Builder $query) => $query
                        ->where('guard_name', CmsRoles::guard())
                        ->whereIn('name', CmsRoles::PERMISSIONS))
                    ->getOptionLabelFromRecordUsing(fn (Permission $record): string => CmsRoles::permissionLabel($record->name))
                    ->columns(2),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name')->label(__('filament-cms::access.role_name'))
                    ->formatStateUsing(fn (string $state): string => CmsRoles::roleLabel($state)),
                TextColumn::make('permissions_count')->label(__('filament-cms::access.screens'))
                    ->formatStateUsing(fn (int $state, Role $record): string => $record->name === CmsRoles::SUPER_ADMIN
                        ? __('filament-cms::access.all_screens')
                        : (string) $state),
                TextColumn::make('users_count')->label(__('filament-cms::access.people')),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn (Role $record): bool => $record->name === CmsRoles::SUPER_ADMIN)
                    ->after(fn () => CmsRoles::forget()),
                DeleteAction::make()
                    ->hidden(fn (Role $record): bool => self::isBuiltIn($record))
                    ->after(fn () => CmsRoles::forget()),
            ]);
    }

    private static function isBuiltIn(?Model $record): bool
    {
        return $record instanceof Role && in_array($record->name, CmsRoles::BUILT_IN, true);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRoles::route('/')];
    }
}
