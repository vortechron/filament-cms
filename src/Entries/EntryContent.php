<?php

namespace Vortechron\FilamentCms\Entries;

use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentCms\Models\Document;

/**
 * The stored value of an entry, for DocumentStore:
 *
 *     ['version' => 2, 'fields' => [...], 'sections' => [...]]
 *
 * A save may send only one part: the page builder sends the sections, the
 * entry form the fields. The part not sent keeps its stored value. Fields
 * are cleaned by the type's fields (Field::cleanFields: unknown names
 * dropped, a bad value becomes the field's default); sections are kept only
 * when the type has a body.
 */
final class EntryContent
{
    /**
     * The sent parts over the stored ones.
     *
     * @param  array<mixed>  $value
     * @return array{version: int, fields: mixed, sections: mixed}
     */
    public static function merge(Document $entry, array $value): array
    {
        $stored = is_array($entry->content) ? $entry->content : [];
        // A bare list is the sections of format 1.
        $sent = array_is_list($value) && $value !== [] ? ['sections' => $value] : $value;

        return [
            'version' => PageValue::VERSION,
            'fields' => array_key_exists('fields', $sent) ? $sent['fields'] : ($stored['fields'] ?? []),
            'sections' => array_key_exists('sections', $sent) ? $sent['sections'] : ($stored['sections'] ?? []),
        ];
    }

    /**
     * What is wrong with a merged value before it is cleaned.
     *
     * @param  array{fields: mixed}  $value
     * @return array<string, string>
     */
    public static function errors(Document $entry, array $value): array
    {
        if (app(EntryTypes::class)->get($entry->entry_type) === null) {
            return ['entry_type' => __('filament-cms::entries.unknown_type')];
        }

        return is_array($value['fields']) ? [] : ['fields' => __('filament-cms::entries.bad_fields')];
    }

    /**
     * The value to store: the cleaned fields, and the cleaned (and
     * guarded) sections when the type has a body.
     *
     * @param  array{fields: array<mixed>}  $value  a merged value that has no errors
     * @param  array{version: int, sections: list<array<string, mixed>>}  $page  its sections, cleaned
     * @return array{version: int, fields: array<string, mixed>, sections: list<array<string, mixed>>}
     */
    public static function clean(Document $entry, array $value, array $page): array
    {
        $type = app(EntryTypes::class)->get($entry->entry_type);

        return [
            'version' => PageValue::VERSION,
            'fields' => Field::cleanFields($type?->getFields() ?? [], $value['fields']),
            'sections' => $type?->hasBody() ? $page['sections'] : [],
        ];
    }
}
