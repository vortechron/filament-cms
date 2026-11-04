<?php

namespace Vortechron\FilamentCms\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Vortechron\FilamentCms\Access\CmsAccess;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Entries\StoredEntryTypes;
use Vortechron\FilamentCms\FilamentCmsPlugin;

/**
 * Content types made in the panel, like WordPress post types: a key, the
 * names, an address for the entries' pages and the fields. Each type gets
 * its own screen under Content and a list block in the builder at once.
 * Only someone with "cms.types" (the super admin) opens it.
 *
 * A saved type keeps its key. A type that still has entries can not be
 * removed. Removing a field leaves its values in the entries, unseen.
 *
 * @property-read Schema $form
 */
class EditContentTypes extends Page
{
    protected static ?string $slug = 'content-types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 50;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

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

    public function getTitle(): string
    {
        return __('filament-cms::types.title');
    }

    public function mount(): void
    {
        $this->form->fill(['types' => array_map(
            fn (array $definition): array => [...$definition, 'saved_key' => $definition['key'] ?? null],
            StoredEntryTypes::definitions(),
        )]);
    }

    public function form(Schema $schema): Schema
    {
        $codeTypes = app(EntryTypes::class)->inCode();

        return $schema
            ->components([
                Text::make(__('filament-cms::types.intro')),
                Text::make(__('filament-cms::types.code_types', ['types' => implode(', ', array_map(fn (EntryType $type): string => $type->getPluralLabel(), $codeTypes))]))
                    ->visible($codeTypes !== []),
                Repeater::make('types')
                    ->hiddenLabel()
                    ->addActionLabel(__('filament-cms::types.add'))
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => filled($state['label'] ?? null) ? $state['label'] : null)
                    ->columns(2)
                    ->schema([
                        Hidden::make('saved_key'),
                        TextInput::make('key')->label(__('filament-cms::types.key'))
                            ->helperText(__('filament-cms::types.key_help'))
                            ->required()->maxLength(40)->regex('/^[a-z][a-z0-9-]*$/')
                            ->disabled(fn (Get $get): bool => filled($get('saved_key')))
                            ->dehydrated(),
                        TextInput::make('label')->label(__('filament-cms::types.label'))->required()->maxLength(60),
                        TextInput::make('plural_label')->label(__('filament-cms::types.plural_label'))->maxLength(60),
                        TextInput::make('path')->label(__('filament-cms::types.path'))
                            ->helperText(__('filament-cms::types.path_help'))
                            ->placeholder('/products')->maxLength(120),
                        Select::make('sort')->label(__('filament-cms::types.sort'))
                            ->options([
                                'newest' => __('filament-cms::entries.order_newest'),
                                'title' => __('filament-cms::entries.order_title'),
                                'manual' => __('filament-cms::entries.order_manual'),
                            ])
                            ->default('newest')->required()->selectablePlaceholder(false),
                        Select::make('icon')->label(__('filament-cms::types.icon'))
                            ->options(array_combine(StoredEntryTypes::ICONS, array_map(
                                fn (string $icon): string => Str::headline(Str::after($icon, 'heroicon-o-')),
                                StoredEntryTypes::ICONS,
                            )))
                            ->default(StoredEntryTypes::ICONS[0])->selectablePlaceholder(false),
                        Toggle::make('body')->label(__('filament-cms::types.body'))->columnSpanFull(),
                        Repeater::make('fields')->label(__('filament-cms::types.fields'))
                            ->addActionLabel(__('filament-cms::types.add_field'))
                            ->columnSpanFull()
                            ->columns(3)
                            ->reorderable()
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('label')->label(__('filament-cms::types.field_label'))->required()->maxLength(60)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                        if (blank($get('name')) && filled($state)) {
                                            $set('name', Str::slug($state, '_'));
                                        }
                                    }),
                                TextInput::make('name')->label(__('filament-cms::types.field_name'))
                                    ->helperText(__('filament-cms::types.field_name_help'))
                                    ->required()->maxLength(40)->regex('/^[a-z][a-z0-9_]*$/')
                                    ->notIn(EntryType::RESERVED_FIELDS),
                                Select::make('type')->label(__('filament-cms::types.field_type'))
                                    ->options(array_combine(StoredEntryTypes::FIELD_TYPES, array_map(
                                        fn (string $type): string => (string) __("filament-cms::types.field_types.{$type}"),
                                        StoredEntryTypes::FIELD_TYPES,
                                    )))
                                    ->default('text')->required()->selectablePlaceholder(false)->live(),
                                Textarea::make('options')->label(__('filament-cms::types.field_options'))
                                    ->rows(3)->columnSpanFull()
                                    ->visible(fn (Get $get): bool => $get('type') === 'select'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('filament-cms::cms.save'))
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ])->sticky()->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $types = app(EntryTypes::class);
        [$definitions, $problem] = StoredEntryTypes::clean($this->form->getState()['types'] ?? [], $types->inCode());

        if ($problem !== null) {
            Notification::make()->danger()->title($problem)->send();

            return;
        }

        app(ContentStore::class)->save(StoredEntryTypes::KEY, ['types' => $definitions], Filament::auth()->user());
        $types->flushStored();

        $this->mount();

        Notification::make()->success()->title(__('filament-cms::types.saved'))->send();
    }
}
