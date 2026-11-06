<?php

namespace Vortechron\FilamentCms\Filament\Resources\Entries;

use Filament\Forms\Components\Field as FormField;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Str;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\CmsImage;
use Vortechron\FilamentCms\CmsUrl;
use Vortechron\FilamentCms\Entries\EntryPictures;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Filament\Schemas\TranslatableText;
use Vortechron\FilamentCms\Models\Entry;

/**
 * The entry form, made from the type's fields: text, textarea, select,
 * toggle, number, link (CmsUrl), image (CmsImage, stored as its public
 * address) and list (a Repeater of its fields). The fields sit under the
 * `fields` key of the form state.
 */
final class EntryForm
{
    /**
     * @return list<mixed>
     */
    public static function components(EntryType $type): array
    {
        return [
            TranslatableText::field(
                TextInput::make('title')->label(__('filament-cms::entries.title'))->required()->maxLength(120),
                fn (string $locale) => TextInput::make("translations.{$locale}.title")->label(__('filament-cms::entries.title'))->maxLength(120),
            ),
            ...($type->getPath() === null ? [] : [
                TextInput::make('slug')
                    ->label(__('filament-cms::entries.slug'))
                    ->helperText(__('filament-cms::entries.slug_help'))
                    ->prefix(rtrim(url($type->getPath()), '/').'/')
                    ->maxLength(80),
            ]),
            Toggle::make('is_published')->label(__('filament-cms::entries.published'))->default(false),
            ...array_map(function (Field $field) {
                $definition = $field->toArray();
                $definition['name'] = 'fields.'.$field->name;
                $original = self::component($definition);
                if (! $field->isTranslatable()) {
                    return $original;
                }

                return TranslatableText::field($original, function (string $locale) use ($field, $definition) {
                    $definition['name'] = "translations.{$locale}.fields.{$field->name}";

                    return self::component($definition)->default(null);
                });
            }, $type->getFields()),
            Hidden::make('translation_baseline'),
        ];
    }

    /**
     * The form state of a stored entry: pictures as paths on the CMS disk.
     *
     * @return array<string, mixed>
     */
    public static function fill(EntryType $type, Entry $entry): array
    {
        return [
            'title' => $entry->title,
            'translations' => $entry->settings['translations'] ?? [],
            'translation_baseline' => json_encode($entry->settings['translations'] ?? []),
            'slug' => $entry->path === null ? null : Str::afterLast($entry->path, '/'),
            'is_published' => (bool) $entry->is_published,
            'fields' => self::toForm(self::definitions($type->getFields()), $entry->fieldValues()),
        ];
    }

    /**
     * The form state as EntryStore data: pictures as public addresses.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function data(EntryType $type, array $data, ?Entry $entry = null): array
    {
        return [
            'title' => $data['title'] ?? null,
            ...(isset($data['translations']) ? self::translationChanges($data) : []),
            ...(array_key_exists('slug', $data) ? ['slug' => $data['slug']] : []),
            'is_published' => (bool) ($data['is_published'] ?? false),
            'fields' => self::fromForm(self::definitions($type->getFields()), is_array($data['fields'] ?? null) ? $data['fields'] : [], $entry?->fieldValues() ?? []),
        ];
    }

    private static function translationChanges(array $data): array
    {
        $baseline = is_string($data['translation_baseline'] ?? null) ? json_decode($data['translation_baseline'], true) : ($data['translation_baseline'] ?? []);
        $baseline = is_array($baseline) ? $baseline : [];
        $changes = [];
        foreach ($data['translations'] as $locale => $values) {
            if (array_key_exists('title', $values) && $values['title'] !== ($baseline[$locale]['title'] ?? null)) {
                $changes[$locale]['title'] = $values['title'];
            }
            foreach ($values['fields'] ?? [] as $field => $value) {
                if ($value !== ($baseline[$locale]['fields'][$field] ?? null)) {
                    $changes[$locale]['fields'][$field] = $value;
                }
            }
        }

        return ['translations' => $changes, 'translation_baseline' => $baseline];
    }

    /**
     * @param  array<string, mixed>  $field  Field::toArray()
     */
    private static function component(array $field): FormField
    {
        $component = match ($field['type']) {
            'textarea' => Textarea::make($field['name'])->rows(4)->maxLength(10000),
            'select' => Select::make($field['name'])->options(self::choices($field)),
            'toggle' => Toggle::make($field['name']),
            'number' => TextInput::make($field['name'])->numeric()->minValue($field['min'] ?? null)->maxValue($field['max'] ?? null),
            'link' => CmsUrl::make($field['name']),
            'image' => CmsImage::make($field['name']),
            'list' => Repeater::make($field['name'])->schema(array_map(self::component(...), $field['fields'] ?? []))->maxItems($field['maxItems'] ?? null)->defaultItems(0),
            default => TextInput::make($field['name'])->maxLength(500),
        };

        return $component->label($field['label'])->default($field['type'] === 'image' ? null : ($field['default'] ?? null));
    }

    /**
     * A select's choices by value. An empty choice (a link's "none") is left
     * out: the select's placeholder stands for it, and a cleared select is
     * saved as the field's default, that empty choice.
     *
     * @param  array<string, mixed>  $field  Field::toArray()
     * @return array<string, string>
     */
    public static function choices(array $field): array
    {
        $choices = [];

        foreach ($field['options'] ?? [] as $option) {
            if ($option['value'] !== '') {
                $choices[$option['value']] = $option['label'];
            }
        }

        return $choices;
    }

    /**
     * @param  list<Field>  $fields
     * @return list<array<string, mixed>>
     */
    private static function definitions(array $fields): array
    {
        return array_map(fn (Field $field): array => $field->toArray(), $fields);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function toForm(array $fields, array $values): array
    {
        foreach ($fields as $field) {
            $value = $values[$field['name']] ?? null;

            $values[$field['name']] = match ($field['type']) {
                'image' => EntryPictures::path($value),
                'list' => array_map(fn (mixed $row): array => self::toForm($field['fields'] ?? [], is_array($row) ? $row : []), is_array($value) ? $value : []),
                default => $value,
            };
        }

        return $values;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private static function fromForm(array $fields, array $state, array $stored): array
    {
        foreach ($fields as $field) {
            $value = $state[$field['name']] ?? null;

            $state[$field['name']] = match ($field['type']) {
                'image' => EntryPictures::url($value, $stored[$field['name']] ?? null),
                'list' => array_values(array_map(fn (mixed $row): array => self::fromForm($field['fields'] ?? [], is_array($row) ? $row : [], []), is_array($value) ? $value : [])),
                default => $value,
            };
        }

        return $state;
    }
}
