<?php

namespace Vortechron\FilamentCms\Filament\Pages\ContentTypes;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Entries\StoredEntryTypes;

/**
 * The form of one content type: a key (fixed once saved), the names, an
 * address for the entries' pages, the order, an icon, a body or not, and
 * its fields. Shared by the create and edit pages.
 */
final class ContentTypeForm
{
    /**
     * @return list<mixed>
     */
    public static function components(bool $saved): array
    {
        return [
            Section::make(__('filament-cms::types.type'))->columns(2)->schema([
                TextInput::make('key')->label(__('filament-cms::types.key'))
                    ->helperText(__('filament-cms::types.key_help'))
                    ->required()->maxLength(40)->regex('/^[a-z][a-z0-9-]*$/')
                    ->disabled($saved)
                    ->live(onBlur: true),
                TextInput::make('label')->label(__('filament-cms::types.label'))->required()->maxLength(60)->live(onBlur: true),
                TextInput::make('plural_label')->label(__('filament-cms::types.plural_label'))->maxLength(60)->live(onBlur: true),
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
            ]),
            Section::make(__('filament-cms::types.fields'))->schema([
                Repeater::make('fields')->hiddenLabel()
                    ->addActionLabel(__('filament-cms::types.add_field'))
                    ->columns(3)
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => filled($state['label'] ?? null) ? $state['label'] : null)
                    ->defaultItems(0)
                    ->schema(self::field()),
            ]),
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function field(): array
    {
        return [
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
            Select::make('target')->label(__('filament-cms::types.field_target'))
                ->helperText(__('filament-cms::types.field_target_help'))
                ->options(fn (Get $get): array => self::linkTargets($get('../../key'), $get('../../plural_label') ?: $get('../../label')))
                ->required(fn (Get $get): bool => $get('type') === 'entry')
                ->visible(fn (Get $get): bool => $get('type') === 'entry'),
        ];
    }

    /**
     * What a link field may link to: the saved types, the code's, and the
     * type on the screen itself (an entry may link to another of its kind).
     *
     * @return array<string, string>
     */
    private static function linkTargets(mixed $ownKey, mixed $ownName): array
    {
        $targets = array_map(fn (EntryType $type): string => $type->getPluralLabel(), app(EntryTypes::class)->all());
        $key = is_string($ownKey) ? Str::lower(trim($ownKey)) : '';

        if ($key !== '' && ! isset($targets[$key])) {
            $targets[$key] = is_string($ownName) && trim($ownName) !== '' ? trim($ownName) : $key;
        }

        return $targets;
    }
}
