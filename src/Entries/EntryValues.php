<?php

namespace Vortechron\FilamentCms\Entries;

use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Models\Entry;

/**
 * The dynamic values of one entry, put into the RenderContext when it is
 * drawn (a card, its page): `entry.title`, `entry.url`, and `entry.{field}`
 * for each field that can be shown. Text, textarea and number fields are
 * text, a select is its option's label, a link field is a link, an image
 * field a picture. Toggles and lists are not values.
 */
final class EntryValues
{
    private const TYPES = ['text' => 'text', 'textarea' => 'text', 'number' => 'text', 'select' => 'text', 'link' => 'link', 'image' => 'image'];

    /**
     * @return array<string, string|null>
     */
    public static function of(Entry $entry): array
    {
        $type = $entry->definition();
        $stored = $entry->translatedFieldValues();
        $values = ['entry.title' => $entry->translatedTitle(), 'entry.url' => $type?->getPath() === null ? null : $entry->url()];

        foreach (self::fields($type) as $field) {
            $values["entry.{$field['name']}"] = self::value($field, $stored[$field['name']] ?? null);
        }

        return $values;
    }

    /**
     * The entry.* values of a type, as the builder of its card, single or
     * entry body gets them, with the values of `$preview` as each one's
     * preview (null without an entry).
     *
     * @return list<array{key: string, label: string, type: string, group: string, preview: string|null}>
     */
    public static function definitions(EntryType $type, ?Entry $preview = null): array
    {
        $values = $preview === null ? [] : self::of($preview);
        $labels = ['title' => __('filament-cms::entries.title'), 'url' => __('filament-cms::entries.url')];
        $kinds = ['title' => 'text', 'url' => 'link'];

        foreach (self::fields($type) as $field) {
            $labels[$field['name']] = $field['label'];
            $kinds[$field['name']] = self::TYPES[$field['type']];
        }

        return array_map(fn (string $name): array => [
            'key' => "entry.{$name}",
            'label' => $labels[$name],
            'type' => $kinds[$name],
            'group' => $type->getLabel(),
            'preview' => $values["entry.{$name}"] ?? null,
        ], array_keys($labels));
    }

    /**
     * The fields that are values, as Field::toArray().
     *
     * @return list<array<string, mixed>>
     */
    private static function fields(?EntryType $type): array
    {
        $fields = [];

        foreach ($type?->getFields() ?? [] as $field) {
            if (isset(self::TYPES[$field->type])) {
                $fields[] = $field->toArray();
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function value(array $field, mixed $value): ?string
    {
        // A stored @key points at a site value, and a {key} short code in
        // the words is filled with one; one level only.
        $plain = new RenderContext;

        return match ($field['type']) {
            'link' => app(DynamicValues::class)->link($value, $plain),
            'image' => app(DynamicValues::class)->media($value, $plain),
            'select' => self::label($field, $value),
            'text', 'textarea' => is_scalar($value) && ! is_bool($value) ? app(DynamicValues::class)->fill((string) $value, $plain) : null,
            default => is_scalar($value) && ! is_bool($value) ? (string) $value : null,
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function label(array $field, mixed $value): ?string
    {
        foreach ($field['options'] ?? [] as $option) {
            if ($option['value'] === $value) {
                return $option['label'] !== '' ? $option['label'] : null;
            }
        }

        return null;
    }
}
