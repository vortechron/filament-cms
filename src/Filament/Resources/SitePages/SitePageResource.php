<?php

namespace Vortechron\FilamentCms\Filament\Resources\SitePages;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Vortechron\FilamentCms\Access\CmsAccess;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\ManageSitePages;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SiteLanguages;

/**
 * The client's pages: a title and an address here, the content in the page
 * builder (the "Bina" button).
 */
class SitePageResource extends Resource
{
    protected static ?string $model = SitePage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?int $navigationSort = -10;

    public static function canAccess(): bool
    {
        return parent::canAccess() && CmsAccess::allows('pages');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-cms::cms.content');
    }

    public static function getModelLabel(): string
    {
        return __('filament-cms::pages.page');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-cms::pages.pages');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make(__('filament-cms::languages.setup_needed'))
                    ->visible(fn (?SitePage $record): bool => SiteLanguages::enabled() && $record !== null && $record->shared_format !== 1),
                PageMetadata::text('title', __('filament-cms::pages.title'), 120, required: true),
                TextInput::make('path')
                    ->label(__('filament-cms::pages.path'))
                    ->helperText(__('filament-cms::pages.path_help'))
                    ->prefix(fn (): string => rtrim(url('/'), '/'))
                    ->required()
                    ->maxLength(120)
                    ->dehydrateStateUsing(fn (?string $state, ?SitePage $record): string => SiteLanguages::enabled() ? SiteLanguages::path((string) $state, $record?->language() ?? SiteLanguages::default()) : SitePage::cleanPath($state))
                    ->rule(fn (?SitePage $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $path = is_string($value) ? $value : '';
                        if (SiteLanguages::enabled()) {
                            $path = SiteLanguages::path($path, $record?->language() ?? SiteLanguages::default());
                        }
                        if (Document::pathTaken($path, $record)) {
                            $fail(__('filament-cms::pages.path_taken'));
                        }
                    }),
                Toggle::make('is_published')->label(__('filament-cms::pages.published'))->default(false),
                ...PageMetadata::languageAddresses(),
                Section::make(__('filament-cms::pages.seo'))
                    ->collapsed()
                    ->schema([
                        PageMetadata::text('seo_title', __('filament-cms::settings.seo_title'), 70),
                        PageMetadata::text('seo_description', __('filament-cms::settings.seo_description'), 170, multiline: true),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('translation_of'))
            ->defaultSort('path')
            ->columns([
                TextColumn::make('title')->label(__('filament-cms::pages.title'))->searchable(),
                TextColumn::make('locale')->label(__('filament-cms::languages.language'))->state(fn (SitePage $record): string => SiteLanguages::labels()[$record->language()] ?? $record->language())->badge()->visible(fn (): bool => SiteLanguages::enabled()),
                TextColumn::make('path')
                    ->label(__('filament-cms::pages.path'))
                    ->url(fn (SitePage $record): string => $record->url(), shouldOpenInNewTab: true),
                ToggleColumn::make('is_published')->label(__('filament-cms::pages.published'))
                    ->state(fn (SitePage $record, ManageSitePages $livewire): bool => $livewire->publicationState($record))
                    ->disabled(fn (SitePage $record): bool => ! static::canEdit($record))
                    ->updateStateUsing(fn (SitePage $record, bool $state, ManageSitePages $livewire): bool => $livewire->savePublication($record, $state)),
                TextColumn::make('updated_at')->label(__('filament-cms::pages.updated'))->since()->sortable(),
            ])
            ->recordUrl(fn (SitePage $record): string => BuildSitePage::getUrl(['record' => $record]))
            ->recordActions([
                Action::make('build')
                    ->label(__('filament-cms::pages.build'))
                    ->icon(Heroicon::OutlinedPaintBrush)
                    ->button()
                    ->url(fn (SitePage $record): string => BuildSitePage::getUrl(['record' => $record])),
                EditAction::make()
                    ->mutateRecordDataUsing(fn (SitePage $record, ManageSitePages $livewire): array => $livewire->metadataForEdit($record))
                    ->using(fn (SitePage $record, array $data, ManageSitePages $livewire): SitePage => $livewire->saveMetadata($record, $data)),
                static::restoreAction(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Brings back an older version of a page's content. Developers only.
     */
    public static function restoreAction(): Action
    {
        return Action::make('restore')
            ->label(__('filament-cms::cms.restore'))
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->visible(fn (): bool => FilamentCmsPlugin::get()->isDeveloper(Filament::auth()->user()))
            ->modalSubmitActionLabel(__('filament-cms::cms.restore_submit'))
            ->fillForm(function (SitePage $record, ManageSitePages $livewire): array {
                $livewire->metadataForEdit($record);

                return [];
            })
            ->schema(fn (SitePage $record): array => [
                Select::make('revision')
                    ->label(__('filament-cms::cms.revision'))
                    ->options(fn (): array => self::revisionOptions($record))
                    ->in(fn (): array => array_keys(self::revisionOptions($record)))
                    ->required(),
            ])
            ->action(function (SitePage $record, array $data, ManageSitePages $livewire): void {
                abort_unless(static::canEdit($record) && FilamentCmsPlugin::get()->isDeveloper(Filament::auth()->user()), 403);
                $revision = CmsRevision::query()->where('key', $record->revisionKey())->findOrFail($data['revision']);
                $result = app(DocumentStore::class)->restore($record, $revision, Filament::auth()->user(), expectedVersion: $livewire->metadataBaselines[$record->getKey()]['version'] ?? null);
                if (! $result['ok']) {
                    $livewire->refuseSave($result['errors'] ?? []);
                }

                $livewire->tableVersions[$record->getKey()] = $result['edit_version'] ?? (int) $record->edit_version;
                Notification::make()->success()->title(__('filament-cms::cms.restored'))->send();
            });
    }

    /**
     * @return array<int, string>
     */
    private static function revisionOptions(SitePage $page): array
    {
        return CmsRevision::query()
            ->with('user')
            ->where('key', $page->revisionKey())
            ->latest('id')
            ->get()
            ->mapWithKeys(fn (CmsRevision $revision): array => [
                $revision->getKey() => trim($revision->created_at?->timezone(config('app.timezone'))->format('j M Y, g:i a').' · '.($revision->user?->name ?? '')),
            ])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSitePages::route('/'),
            'build' => BuildSitePage::route('/{record}/build'),
        ];
    }
}
