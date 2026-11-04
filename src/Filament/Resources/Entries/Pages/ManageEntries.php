<?php

namespace Vortechron\FilamentCms\Filament\Resources\Entries\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryTemplates;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Filament\Resources\Entries\EntryForm;
use Vortechron\FilamentCms\Filament\Resources\Entries\EntryResource;
use Vortechron\FilamentCms\Filament\Resources\Entries\EntryTable;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;

/**
 * The screen of one entry type, /admin/entries/{type}: its entries, with
 * columns and filters from its fields (EntryTable), a form made from its fields, "Bina" for the body, and "Reka kad" / "Reka halaman
 * entri" for the type's card and entry page designs.
 */
class ManageEntries extends ManageRecords
{
    protected static string $resource = EntryResource::class;

    #[Locked]
    public string $type = '';

    public function mount(string $type = ''): void
    {
        abort_unless(app(EntryTypes::class)->has($type), 404);

        $this->type = $type;

        parent::mount();
    }

    public function entryType(): EntryType
    {
        return app(EntryTypes::class)->get($this->type) ?? abort(404);
    }

    public function getTitle(): string
    {
        return $this->entryType()->getPluralLabel();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(EntryForm::components($this->entryType()))->columns(1);
    }

    public function table(Table $table): Table
    {
        $type = $this->entryType();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('entry_type', $type->key))
            ->defaultSort(fn (Builder $query): Builder => $query->ordered($type->getSort()))
            ->reorderable('sort', $type->getSort() === 'manual')
            ->modelLabel($type->getLabel())
            ->pluralModelLabel($type->getPluralLabel())
            ->columns([
                ...EntryTable::before($type),
                TextColumn::make('title')->label(__('filament-cms::entries.title'))->searchable(),
                ...EntryTable::after($type),
                IconColumn::make('is_published')->label(__('filament-cms::entries.published'))->boolean(),
                TextColumn::make('updated_at')->label(__('filament-cms::entries.updated'))->since()->sortable(),
            ])
            ->filters(EntryTable::filters($type))
            ->recordActions([
                Action::make('build')
                    ->label(__('filament-cms::pages.build'))
                    ->icon(Heroicon::OutlinedPaintBrush)
                    ->button()
                    ->visible(fn (Entry $record): bool => $type->hasBody() && $this->plugin()->hasSitePages() && BuildSitePage::mayBuild($record))
                    ->url(fn (Entry $record): string => BuildSitePage::getUrl(['record' => $record])),
                EditAction::make()
                    ->mutateRecordDataUsing(fn (Entry $record): array => EntryForm::fill($type, $record))
                    ->using(fn (Entry $record, array $data): Entry => $this->saveEntry($record, EntryForm::data($type, $data, $record))),
                $this->publishAction('publish', true),
                $this->publishAction('unpublish', false),
                DeleteAction::make(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $type = $this->entryType();

        return [
            $this->designAction('designCard', Document::CARD, Heroicon::OutlinedSquare2Stack),
            $this->designAction('designSingle', Document::SINGLE, Heroicon::OutlinedDocumentText)->visible(fn (): bool => $type->getPath() !== null && $this->mayDesign()),
            CreateAction::make()
                ->label(__('filament-cms::entries.new', ['type' => $type->getLabel()]))
                ->using(fn (array $data): Entry => $this->saveEntry(new Entry(['entry_type' => $type->key]), EntryForm::data($type, $data))),
        ];
    }

    private function publishAction(string $name, bool $published): Action
    {
        return Action::make($name)
            ->label(__("filament-cms::entries.{$name}"))
            ->icon($published ? Heroicon::OutlinedEye : Heroicon::OutlinedEyeSlash)
            ->color($published ? 'success' : 'gray')
            ->authorize(fn (Entry $record): bool => EntryResource::canEdit($record))
            ->visible(fn (Entry $record): bool => (bool) $record->is_published !== $published)
            ->action(function (Entry $record) use ($name, $published): void {
                $this->saveEntry($record, ['is_published' => $published]);
                Notification::make()->success()->title(__("filament-cms::entries.{$name}_done"))->send();
            });
    }

    /**
     * Opens the builder for the type's card or entry page design, made the
     * first time.
     */
    private function designAction(string $name, string $kind, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label(__("filament-cms::entries.{$name}"))
            ->icon($icon)
            ->color('gray')
            ->visible(fn (): bool => $this->mayDesign())
            ->action(function () use ($kind): void {
                abort_unless($this->mayDesign(), 403);

                $this->redirect(BuildSitePage::getUrl(['record' => EntryTemplates::findOrCreate($this->entryType(), $kind)]));
            });
    }

    private function mayDesign(): bool
    {
        return $this->plugin()->hasSitePages() && BuildSitePage::mayBuild(null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveEntry(Entry $entry, array $data): Entry
    {
        $result = app(EntryStore::class)->save($entry, $data, Filament::auth()->user());

        if (! $result['ok']) {
            Notification::make()->danger()->title(implode(' ', $result['errors'] ?? []))->send();

            throw new Halt;
        }

        return $entry;
    }

    private function plugin(): FilamentCmsPlugin
    {
        return FilamentCmsPlugin::get();
    }
}
