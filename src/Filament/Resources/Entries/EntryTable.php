<?php

namespace Vortechron\FilamentCms\Filament\Resources\Entries;

use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryValues;
use Vortechron\FilamentCms\Models\Entry;

/**
 * The columns and filters an entry type's screen gets from its fields: the
 * first picture before the title, then up to MAX_COLUMNS short fields
 * (choices, links to an entry, numbers) after it, each shown as the site
 * shows it (EntryValues), and a filter for each choice or link, so the
 * shop finds "every item in Alat tulis". Each is named `field_{name}`.
 */
final class EntryTable
{
    public const MAX_COLUMNS = 3;

    /**
     * @return list<Column>
     */
    public static function before(EntryType $type): array
    {
        $picture = $type->firstField('image');

        return $picture === null ? [] : [
            ImageColumn::make('field_'.$picture->name)->label('')->square()->state(self::value($picture)),
        ];
    }

    /**
     * @return list<Column>
     */
    public static function after(EntryType $type): array
    {
        $short = array_filter($type->getFields(), fn (Field $field): bool => in_array($field->type, ['select', 'number'], true));

        return array_map(
            fn (Field $field): Column => TextColumn::make('field_'.$field->name)
                ->label(self::label($field))
                ->placeholder('—')
                ->state(self::value($field)),
            array_slice(array_values($short), 0, self::MAX_COLUMNS),
        );
    }

    /**
     * @return list<SelectFilter>
     */
    public static function filters(EntryType $type): array
    {
        $selects = array_filter($type->getFields(), fn (Field $field): bool => $field->type === 'select');

        return array_map(
            fn (Field $field): SelectFilter => SelectFilter::make('field_'.$field->name)
                ->label(self::label($field))
                ->options(EntryForm::choices($field->toArray()))
                ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                    ? $query
                    : $query->where('content->fields->'.$field->name, (string) $data['value'])),
            array_values($selects),
        );
    }

    /**
     * @return callable(Entry): ?string
     */
    private static function value(Field $field): callable
    {
        return fn (Entry $record): ?string => EntryValues::of($record)['entry.'.$field->name] ?? null;
    }

    private static function label(Field $field): string
    {
        return (string) $field->toArray()['label'];
    }
}
