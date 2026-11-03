<?php

namespace Vortechron\FilamentCms\Entries;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
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
    public const KINDS = [Document::ENTRY, Document::CARD, Document::SINGLE];

    /**
     * The type of an entry, card or single, when it is registered.
     */
    public static function type(Document $document): ?EntryType
    {
        return in_array($document->kind, self::KINDS, true) ? app(EntryTypes::class)->get($document->entry_type) : null;
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

        $entries = fn () => Entry::query()->ofType($type->key)->ordered($type->getSort());

        return $entries()->published()->first() ?? $entries()->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function dynamicValues(Document $document): array
    {
        $type = self::type($document);

        return $type === null ? [] : EntryValues::definitions($type, self::previewEntry($document));
    }

    /**
     * The builder preview: an entry's page with its unsaved body, or a card
     * or single drawn with the first entry's values.
     *
     * @param  list<array<string, mixed>>  $sections
     */
    public static function preview(Document $document, array $sections): ?View
    {
        $entry = self::previewEntry($document);

        if ($document->kind === Document::ENTRY) {
            return $entry === null ? null : app(EntryPageRenderer::class)->view($entry, $sections, forBuilder: true);
        }

        return app(SitePageRenderer::class)->preview($document, $sections, $entry === null ? [] : EntryValues::of($entry));
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
