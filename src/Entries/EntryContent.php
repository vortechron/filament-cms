<?php

namespace Vortechron\FilamentCms\Entries;

use Closure;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentCms\Documents\ContentHandler;
use Vortechron\FilamentCms\Documents\PageValueContent;
use Vortechron\FilamentCms\Models\Document;

/**
 * The stored value of an entry, for DocumentStore:
 *
 *     ['version' => 2, 'fields' => [...], 'sections' => [...]]
 *
 * A save may send only one part: the page builder sends the sections, the
 * entry form the fields. The part not sent keeps its stored value. Fields
 * are cleaned by the type's fields (Field::cleanFields: unknown names
 * dropped, a bad value becomes the field's default); sections are checked
 * and cleaned as a page value, and kept only when the type has a body. A
 * restored version brings back its sections and keeps the stored fields.
 */
final class EntryContent implements ContentHandler
{
    public function __construct(private readonly PageValueContent $pages) {}

    /**
     * The sent parts over the stored ones.
     *
     * @return array{version: int, fields: mixed, sections: mixed}
     */
    public function merge(Document $document, array $value): array
    {
        $stored = is_array($document->content) ? $document->content : [];
        // A bare list is the sections of format 1.
        $sent = array_is_list($value) && $value !== [] ? ['sections' => $value] : $value;

        return [
            'version' => PageValue::VERSION,
            'fields' => array_key_exists('fields', $sent) ? $sent['fields'] : ($stored['fields'] ?? []),
            'sections' => array_key_exists('sections', $sent) ? $sent['sections'] : ($stored['sections'] ?? []),
        ];
    }

    /**
     * An unregistered type, fields that are not a list of values, then
     * whatever is wrong with the sections.
     */
    public function errors(Document $document, array $value): array
    {
        if (app(EntryTypes::class)->get($document->entry_type) === null) {
            return ['entry_type' => __('filament-cms::entries.unknown_type')];
        }

        if (! is_array($value['fields'] ?? null)) {
            return ['fields' => __('filament-cms::entries.bad_fields')];
        }

        return $this->pages->errors($document, $value);
    }

    /**
     * The cleaned fields, and the cleaned (and guarded) sections when the
     * type has a body.
     *
     * @return array{version: int, fields: array<string, mixed>, sections: list<array<string, mixed>>}
     */
    public function clean(Document $document, array $value, Closure $stored, bool $developer): array
    {
        $type = app(EntryTypes::class)->get($document->entry_type);

        return [
            'version' => PageValue::VERSION,
            'fields' => Field::cleanFields($type?->getFields() ?? [], $value['fields']),
            'sections' => $type?->hasBody() ? $this->pages->clean($document, $value, $stored, $developer)['sections'] : [],
        ];
    }

    public function restored(array $content): array
    {
        return ['sections' => Document::sectionsOf($content)];
    }
}
