<?php

namespace Vortechron\FilamentCms\Filament\Resources\SiteSections;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Vortechron\FilamentCms\Access\CmsAccess;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Filament\Resources\SiteSections\Pages\ManageSiteSections;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\SectionLibrary;

/**
 * The "Seksyen" screen: synced sections (one section many pages show) and
 * saved sections (templates in the builder's add panel). Both are made in
 * the page builder; here they are renamed, deleted, or built ("Bina"). The same people who may edit pages may open it.
 * It shows in the menu only once one exists.
 */
class SiteSectionResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $slug = 'site-sections';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static ?int $navigationSort = -9;

    public static function canAccess(): bool
    {
        return parent::canAccess() && CmsAccess::allows('sections') && BuildSitePage::mayBuild(null);
    }

    /**
     * In the menu only once a synced or saved section exists: they are made
     * in the page builder, so an empty list here only puzzles the shop.
     */
    public static function shouldRegisterNavigation(): bool
    {
        if (! parent::shouldRegisterNavigation() || ! static::canAccess()) {
            return false;
        }

        try {
            return static::getEloquentQuery()->exists();
        } catch (QueryException) {
            return false;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('kind', CmsKind::values(fn (CmsKind $kind): bool => $kind->holdsOneSection()));
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-cms::cms.content');
    }

    public static function getModelLabel(): string
    {
        return __('filament-cms::sections.section');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-cms::sections.sections');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([TextInput::make('title')->label(__('filament-cms::sections.title'))->required()->maxLength(120)])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->emptyStateHeading(__('filament-cms::sections.empty'))
            ->emptyStateDescription(__('filament-cms::sections.empty_help'))
            ->columns([
                TextColumn::make('title')->label(__('filament-cms::sections.title'))->searchable(),
                TextColumn::make('kind')
                    ->label(__('filament-cms::sections.kind'))
                    ->badge()
                    ->color(fn (string $state): string => $state === Document::SECTION ? 'primary' : 'gray')
                    ->formatStateUsing(fn (string $state): string => CmsKind::of($state)?->label() ?? $state),
                TextColumn::make('updated_at')->label(__('filament-cms::pages.updated'))->since()->sortable(),
            ])
            ->recordActions([
                Action::make('build')
                    ->label(__('filament-cms::pages.build'))
                    ->icon(Heroicon::OutlinedPaintBrush)
                    ->button()
                    ->url(fn (Document $record): string => BuildSitePage::getUrl(['record' => $record])),
                EditAction::make()->label(__('filament-cms::sections.rename')),
                DeleteAction::make()->modalDescription(fn (Document $record): string => self::deleteWarning($record)),
            ]);
    }

    /**
     * Which pages lose a synced section when it is deleted.
     */
    private static function deleteWarning(Document $record): string
    {
        $users = $record->kind === Document::SECTION ? app(SectionLibrary::class)->usedBy($record) : collect();

        return $users->isEmpty()
            ? __('filament-cms::sections.not_used')
            : __('filament-cms::sections.used_by', ['pages' => $users->pluck('title')->implode(', ')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSiteSections::route('/')];
    }
}
