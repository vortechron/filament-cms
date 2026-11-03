<?php

namespace Vortechron\FilamentCms\Forms;

use Vortechron\FilamentBlockEditor\BlockNote\BlockNoteDocument;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Content\PageContent;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentBlockEditor\Content\SectionResolver;
use Vortechron\FilamentBlockEditor\Forms\FormDefinition;
use Vortechron\FilamentCms\Models\Document;

/**
 * Finds the stored form block a visitor sent, by its `_form` key
 * (`{sectionId}.{blockId}`, or `{blockId}` for a form drawn outside a
 * section). Only sections visitors see count, in this order: the
 * document's own sections, an entry's page template (its type's `single`
 * document), the header's and the footer's, then the synced sections any
 * of them shows. The form always comes from storage,
 * never from the request.
 */
final class FormLocator
{
    private const KEY = '/^(?:([A-Za-z0-9_-]{1,40})\.)?([A-Za-z0-9_-]{1,64})$/';

    public function __construct(private readonly SectionResolver $resolver) {}

    /**
     * @param  bool  $shownOnly  false to look in hidden sections too (the
     *                           panel's labels for an older submission)
     */
    public function find(Document $document, mixed $key, bool $shownOnly = true): ?LocatedForm
    {
        if (! is_string($key) || strlen($key) > 100 || preg_match(self::KEY, $key, $match) !== 1) {
            return null;
        }

        [$sectionId, $blockId] = [$match[1] !== '' ? $match[1] : null, $match[2]];

        foreach ($this->sections($document, $shownOnly) as $section) {
            if ($sectionId !== null && $section['id'] !== $sectionId) {
                continue;
            }

            $block = self::formBlock($section['data']['document'] ?? [], $blockId);

            if ($block !== null) {
                return new LocatedForm(FormDefinition::fromProps($block['props'] ?? []), $key, $blockId);
            }
        }

        return null;
    }

    /**
     * The rich sections to look in, in order. A synced section is found by
     * the id of the `ref` that shows it, as the page draws it (only one
     * level, like the renderer).
     *
     * @return list<array<string, mixed>>
     */
    private function sections(Document $document, bool $shownOnly): array
    {
        $shown = [
            ...self::sectionsOf($document, $shownOnly),
            ...self::sectionsOf(self::entryTemplate($document), $shownOnly),
            ...self::sectionsOf(Document::firstOfKind(Document::HEADER), $shownOnly),
            ...self::sectionsOf(Document::firstOfKind(Document::FOOTER), $shownOnly),
        ];
        $rich = array_filter($shown, fn (array $section): bool => $section['type'] === PageContent::TYPE);
        $synced = [];

        foreach ($shown as $section) {
            $resolved = $section['type'] === PageContent::REF ? $this->resolver->resolve($section['ref']) : null;

            if ($resolved !== null) {
                $synced[] = [...$resolved, 'id' => $section['id']];
            }
        }

        return [...array_values($rich), ...$synced];
    }

    /**
     * An entry's page is drawn with its type's `single` document first.
     */
    private static function entryTemplate(Document $document): ?Document
    {
        if ($document->kind !== Document::ENTRY || $document->entry_type === null) {
            return null;
        }

        return Document::query()->ofKind(Document::SINGLE)->where('entry_type', $document->entry_type)->oldest('id')->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function sectionsOf(?Document $document, bool $shownOnly): array
    {
        $sections = PageValue::normalize($document?->content ?? [])['sections'];

        return $shownOnly ? PageContent::visible($sections) : $sections;
    }

    /**
     * The form block with this id, through every column and indent.
     *
     * @return array<string, mixed>|null
     */
    private static function formBlock(mixed $blocks, string $blockId): ?array
    {
        foreach (is_array($blocks) ? $blocks : [] as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? null) === Blocks::FORM && BlockNoteDocument::blockId($block['id'] ?? null) === $blockId) {
                return $block;
            }

            $child = self::formBlock($block['children'] ?? null, $blockId);

            if ($child !== null) {
                return $child;
            }
        }

        return null;
    }
}
