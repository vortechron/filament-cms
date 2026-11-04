<?php

namespace Vortechron\FilamentCms\Filament\Pages\ContentTypes;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Vortechron\FilamentCms\Access\CmsAccess;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Entries\StoredEntryTypes;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\Entry;

/**
 * Content types made in the panel, like WordPress post types, as a list:
 * tap one to edit it, "New content type" to add one. Types made in code
 * are listed too, marked, and can not be changed here. Each type gets its
 * own screen under Content and a list block in the builder at once. Only
 * someone with "cms.types" (the super admin) opens it.
 */
class ListContentTypes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'content-types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 100;

    public static function canAccess(): bool
    {
        return FilamentCmsPlugin::get()->hasEntries() && CmsAccess::allows('types');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-cms::cms.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-cms::types.title');
    }

    /**
     * The edit and create pages keep "Content types" lit in the menu.
     */
    public static function getNavigationItemActiveRoutePattern(): string
    {
        return static::getRouteName().'*';
    }

    public function getTitle(): string
    {
        return __('filament-cms::types.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label(__('filament-cms::types.add'))->icon(Heroicon::OutlinedPlus)->url(CreateContentType::getUrl()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make(__('filament-cms::types.intro')),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => self::rows())
            ->paginated(false)
            ->emptyStateHeading(__('filament-cms::types.empty'))
            ->columns([
                TextColumn::make('name')->label(__('filament-cms::types.label'))->weight('medium'),
                TextColumn::make('key')->label(__('filament-cms::types.key'))->fontFamily('mono')->color('gray'),
                TextColumn::make('path')->label(__('filament-cms::types.path'))->placeholder(__('filament-cms::types.no_pages')),
                TextColumn::make('fields')->label(__('filament-cms::types.fields')),
                TextColumn::make('entries')->label(__('filament-cms::types.entries')),
                TextColumn::make('source')->label('')->badge()
                    ->color(fn (array $record): string => $record['broken'] ? 'danger' : 'gray')
                    ->state(fn (array $record): ?string => match (true) {
                        $record['broken'] => __('filament-cms::types.broken'),
                        $record['in_code'] => __('filament-cms::types.in_code'),
                        default => null,
                    }),
            ])
            ->recordUrl(fn (array $record): ?string => $record['in_code'] ? null : EditContentType::getUrl(['type' => $record['key']]))
            ->recordActions([
                Action::make('edit')
                    ->label(__('filament-cms::types.edit_short'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->hidden(fn (array $record): bool => $record['in_code'])
                    ->url(fn (array $record): string => EditContentType::getUrl(['type' => $record['key']])),
                Action::make('delete')
                    ->label(__('filament-cms::types.delete'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('filament-cms::types.delete_help'))
                    ->hidden(fn (array $record): bool => $record['in_code'])
                    ->action(fn (array $record) => $this->deleteType($record['key'])),
            ]);
    }

    private function deleteType(string $key): void
    {
        abort_unless(static::canAccess(), 403);

        $problem = StoredEntryTypes::remove($key, app(EntryTypes::class)->inCode(), Filament::auth()->user());

        $problem === null
            ? Notification::make()->success()->title(__('filament-cms::types.deleted'))->send()
            : Notification::make()->danger()->title($problem)->send();
    }

    /**
     * Every saved type as stored, then the code's, by key. A saved type
     * that no longer builds (its address is now the site's, say) is still
     * listed, marked, so it can be opened and fixed or deleted.
     *
     * @return array<string, array{key: string, name: string, path: ?string, fields: int, entries: int, in_code: bool, broken: bool}>
     */
    private static function rows(): array
    {
        $types = app(EntryTypes::class);
        $counts = Entry::query()->toBase()->selectRaw('entry_type, count(*) as total')->groupBy('entry_type')->pluck('total', 'entry_type');
        $rows = [];

        foreach (StoredEntryTypes::definitions() as $definition) {
            $key = (string) ($definition['key'] ?? '');

            if ($key === '' || isset($types->inCode()[$key])) {
                continue;
            }

            $label = trim((string) ($definition['plural_label'] ?? '')) ?: trim((string) ($definition['label'] ?? '')) ?: $key;
            $rows[$key] = [
                'key' => $key,
                'name' => $label,
                'path' => filled($definition['path'] ?? null) ? (string) $definition['path'] : null,
                'fields' => is_array($definition['fields'] ?? null) ? count($definition['fields']) : 0,
                'entries' => (int) ($counts[$key] ?? 0),
                'in_code' => false,
                'broken' => $types->get($key) === null,
            ];
        }

        foreach ($types->inCode() as $key => $type) {
            $rows[$key] = [
                'key' => $key,
                'name' => $type->getPluralLabel(),
                'path' => $type->getPath(),
                'fields' => count($type->getFields()),
                'entries' => (int) ($counts[$key] ?? 0),
                'in_code' => true,
                'broken' => false,
            ];
        }

        return $rows;
    }
}
