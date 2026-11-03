<?php

namespace Vortechron\FilamentCms\Entries;

use Filament\Facades\Filament;
use Vortechron\FilamentCms\Filament\Resources\Entries\EntryResource;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\SitePageRenderer;

/**
 * What the page builder does differently for an entry's body and for a
 * type's card and single designs: their `entry.*` dynamic values (the
 * entry itself, or the type's first entry, as the preview), their preview,
 * and the way back to the type's screen.
 */
final class EntryBuilder
{
    /**
     * The type of an entry, card or single, when it is registered.
     */
    public static function type(Document $document): ?EntryType
    {
        return $document->cmsKind()?->belongsToEntryType() === true ? app(EntryTypes::class)->get($document->entry_type) : null;
    }

    /**
     * An entry's body is built only when its type is registered, has a
     * body, and the entry screens are on.
     */
    public static function buildable(Document $document): bool
    {
        if ($document->kind !== Document::ENTRY) {
            return true;
        }

        return self::type($document)?->hasBody() === true && self::plugin()?->hasEntries() !== false;
    }

    /**
     * The entry whose values the builder shows: the entry itself, or the
     * type's first entry (published ones first) for a card or single.
     */
    public static function previewEntry(Document $document): ?Entry
    {
        $type = self::type($document);

        if ($type === null) {
            return null;
        }

        if ($document->kind === Document::ENTRY) {
            return $document instanceof Entry ? $document : Entry::query()->find($document->getKey());
        }

        return Entry::firstOf($type);
    }

    /**
     * The type's entry.* definitions, without previews: the builder reads
     * them from entryValues().
     *
     * @return list<array<string, mixed>>
     */
    public static function dynamicValues(Document $document): array
    {
        $type = self::type($document);

        return $type === null ? [] : EntryValues::definitions($type);
    }

    /**
     * The entry.* values of the entry the builder shows, or [] when there is none.
     *
     * @return array<string, string|null>
     */
    public static function entryValues(Document $document): array
    {
        $entry = self::previewEntry($document);

        return $entry === null ? [] : EntryValues::of($entry);
    }

    /**
     * The page an entry body or a single design is drawn on: the entry's
     * own page (the first entry's, for a single). Null for other kinds.
     */
    public static function page(Document $document): ?Entry
    {
        return in_array($document->kind, [Document::ENTRY, Document::SINGLE], true) ? self::previewEntry($document) : null;
    }

    /**
     * The builder preview: an entry's page with its unsaved body, or a card
     * or single drawn with the first entry's values.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return string a full HTML document
     */
    public static function preview(Document $document, array $sections): string
    {
        $entry = self::previewEntry($document);

        if ($document->kind === Document::ENTRY) {
            return $entry === null ? '' : app(EntryPageRenderer::class)->view($entry, $sections, forBuilder: true)->render();
        }

        $page = self::page($document);
        $values = [...($page === null ? [] : SitePageRenderer::pageValues($page)), ...self::entryValues($document)];

        if ($document->kind !== Document::CARD) {
            return app(SitePageRenderer::class)->preview($document, $sections, $values)->render();
        }

        // A card is drawn as a card: a list inside it shows its note only, a form is not connected.
        return EntryList::drawingCard(fn (): string => app(SitePageRenderer::class)->preview($document, $sections, EntryList::cardValues([], $values))->render());
    }

    /**
     * The type's screen, when the entry screens are on.
     */
    public static function backUrl(Document $document): ?string
    {
        $type = self::type($document);

        return $type !== null && self::plugin()?->hasEntries() === true ? EntryResource::getUrl('index', ['type' => $type->key]) : null;
    }

    private static function plugin(): ?FilamentCmsPlugin
    {
        $panel = Filament::getCurrentPanel();

        return $panel !== null && $panel->hasPlugin(FilamentCmsPlugin::ID) ? FilamentCmsPlugin::get() : null;
    }
}
