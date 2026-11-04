<?php

namespace Vortechron\FilamentCms\Filament\Resources\Users;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Vortechron\FilamentCms\Access\CmsAccess;
use Vortechron\FilamentCms\Access\CmsRoles;
use Vortechron\FilamentCms\Filament\Resources\Users\Pages\ManageUsers;

/**
 * The people who sign in to the panel, each with one role. Only someone
 * with "cms.users" opens it. Anyone but a super admin sees, makes and
 * changes only people whose role is no stronger than their own (never a
 * super admin: CmsAccess::assignableRoles()), and nobody changes their own
 * role or deletes themselves, so the shop can not lock Vortechron out, or
 * itself.
 */
class UserResource extends Resource
{
    protected static ?string $slug = 'users';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 90;

    public static function getModel(): string
    {
        return (string) config('auth.providers.users.model');
    }

    public static function canAccess(): bool
    {
        return CmsAccess::allows('users');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-cms::access.group');
    }

    public static function getModelLabel(): string
    {
        return __('filament-cms::access.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-cms::access.users');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('roles');

        if (CmsAccess::isSuperAdmin()) {
            return $query;
        }

        $allowed = array_keys(CmsAccess::assignableRoles());

        return $query->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereNotIn('name', $allowed));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('filament-cms::access.name'))->required()->maxLength(120),
                TextInput::make('email')->label(__('filament-cms::access.email'))->email()->required()->maxLength(160)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')->label(__('filament-cms::access.password'))->password()->revealable()
                    ->minLength(8)->maxLength(120)
                    ->required(fn (?Model $record): bool => $record === null)
                    ->helperText(fn (?Model $record): ?string => $record === null ? null : __('filament-cms::access.password_help'))
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Select::make('role')->label(__('filament-cms::access.role'))
                    ->options(fn (): array => CmsAccess::assignableRoles())
                    ->in(fn (): array => array_keys(CmsAccess::assignableRoles()))
                    ->required()
                    ->default(CmsRoles::ADMIN)
                    ->disabled(fn (?Model $record): bool => self::isSelf($record))
                    ->helperText(fn (?Model $record): ?string => self::isSelf($record) ? __('filament-cms::access.own_role') : null)
                    ->afterStateHydrated(function (Select $component, ?Model $record): void {
                        if ($record !== null) {
                            $component->state($record->roles->first()?->name);
                        }
                    })
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(function (Model $record, ?string $state): void {
                        if (! self::isSelf($record) && is_string($state) && array_key_exists($state, CmsAccess::assignableRoles())) {
                            $record->syncRoles([$state]);
                        }
                    }),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('filament-cms::access.name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('filament-cms::access.email'))->searchable(),
                TextColumn::make('roles.name')->label(__('filament-cms::access.role'))->badge()
                    ->formatStateUsing(fn (string $state): string => CmsRoles::roleLabel($state)),
                TextColumn::make('created_at')->label(__('filament-cms::access.added'))->since()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Model $record): bool => self::isSelf($record)),
            ]);
    }

    private static function isSelf(?Model $record): bool
    {
        $user = Filament::auth()->user();

        return $record !== null && $user !== null && $record->getKey() === $user->getAuthIdentifier();
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
