<?php

namespace Vortechron\FilamentCms\Mcp;

use Closure;
use Laravel\Mcp\ResponseFactory;
use Vortechron\FilamentBlockEditor\BlockNote\BlockNoteDocument;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentCms\Entries\EntryBuilder;
use Vortechron\FilamentCms\Models\Document;

/**
 * The section tools: load a document's stored sections, change one, and
 * save the whole value again through DocumentWriter (so the store checks,
 * cleans and keeps a version), as a developer.
 */
final class SectionEditor
{
    public function __construct(private readonly DocumentWriter $writer) {}

    /**
     * A document whose value holds sections, or the error. An entry's body
     * counts when its type has one; its fields stay as stored.
     */
    public static function document(mixed $id): Document|ResponseFactory
    {
        $document = Documents::byId($id, 'document_id', [...DocumentWriter::PAGE_KINDS, Document::ENTRY]);

        if ($document instanceof Document && $document->kind === Document::ENTRY && EntryBuilder::type($document)?->hasBody() !== true) {
            return Reply::refuse('document_id', "Entry {$document->getKey()} has no body: its type {$document->entry_type} has none. Change its fields with save_entry.");
        }

        return $document;
    }

    /**
     * The index of a section by id, or the error.
     *
     * @param  list<array<string, mixed>>  $sections
     */
    public static function find(Document $document, array $sections, mixed $sectionId): int|ResponseFactory
    {
        foreach ($sections as $index => $section) {
            if ($section['id'] === $sectionId) {
                return $index;
            }
        }

        return Reply::refuse('section_id', "Document {$document->getKey()} has no section ".json_encode($sectionId).'. Its sections: '.(implode(', ', array_column($sections, 'id')) ?: 'none').'.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function stored(Document $document): array
    {
        return $document->contentSections();
    }

    /**
     * Saves the changed sections and answers like every write tool, with
     * the new outline of the document.
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, mixed>  $extra
     */
    public function save(Document $document, array $sections, array $extra = []): ResponseFactory
    {
        $result = $this->writer->save($document, ['version' => PageValue::VERSION, 'sections' => array_values($sections)], []);

        if (! $result['ok']) {
            return Reply::refused($result['errors'] ?? []);
        }

        return Reply::saved([...$extra, ...array_diff_key($result, ['ok' => true]), 'sections' => self::outline(self::stored($document))]);
    }

    /**
     * Each section's id, type and first words, so the agent knows the order.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function outline(array $sections): array
    {
        return array_map(fn (array $section): array => [
            'id' => $section['id'],
            'type' => $section['type'],
            ...($section['type'] === 'ref' ? ['ref' => $section['ref']] : [
                'layout' => $section['data']['layout'] ?? null,
                'text' => mb_strimwidth(str_replace("\n", ' ', BlockNoteDocument::text($section['data']['document'] ?? [])), 0, 80, '…'),
            ]),
            ...(($section['hidden'] ?? false) ? ['hidden' => true] : []),
        ], $sections);
    }

    /**
     * Runs a change on a document's stored sections and saves the result.
     *
     * @param  Closure(list<array<string, mixed>>, Document): (list<array<string, mixed>>|ResponseFactory)  $change
     */
    public function edit(mixed $documentId, Closure $change, array $extra = []): ResponseFactory
    {
        $document = self::document($documentId);

        if ($document instanceof ResponseFactory) {
            return $document;
        }

        $sections = $change(self::stored($document), $document);

        return $sections instanceof ResponseFactory ? $sections : $this->save($document, $sections, $extra);
    }
}
