<?php

namespace Vortechron\FilamentCms\Entries;

use Vortechron\FilamentCms\Models\Document;

/**
 * The two designs of an entry type, built in the page builder: its card
 * (how the list block draws one entry) and its single (the top of each
 * entry's own page). Documents of kind "card" and "single" with the type's
 * key as `entry_type`.
 */
final class EntryTemplates
{
    public const KINDS = [Document::CARD, Document::SINGLE];

    public static function find(EntryType|string $type, string $kind): ?Document
    {
        return Document::query()
            ->ofKind($kind)
            ->where('entry_type', $type instanceof EntryType ? $type->key : $type)
            ->oldest('id')
            ->first();
    }

    /**
     * The design, made empty the first time.
     */
    public static function findOrCreate(EntryType $type, string $kind): Document
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);

        return self::find($type, $kind) ?? Document::query()->create([
            'kind' => $kind,
            'entry_type' => $type->key,
            'title' => __("filament-cms::entries.{$kind}_title", ['type' => $type->getLabel()]),
            'content' => ['version' => 2, 'sections' => []],
        ]);
    }
}
