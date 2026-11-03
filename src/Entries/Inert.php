<?php

namespace Vortechron\FilamentCms\Entries;

/**
 * Sections drawn inside the page being built that are not part of its value:
 * a card in the list block, the entry page design above an entry's body.
 * The builder finds the sections it edits by `data-block-id`, so in the
 * builder these drawings carry `data-entry-block-id` instead. Only the
 * renderer writes `<div data-block-id="`; text inside is always escaped, so
 * it never holds `<div`.
 */
final class Inert
{
    public static function html(string $html): string
    {
        return str_replace('<div data-block-id="', '<div data-entry-block-id="', $html);
    }
}
