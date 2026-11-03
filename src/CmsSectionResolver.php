<?php

namespace Vortechron\FilamentCms;

use Vortechron\FilamentBlockEditor\Content\PageContent;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentBlockEditor\Content\SectionResolver;
use Vortechron\FilamentCms\Models\Document;

/**
 * Finds the section a synced section (`['type' => 'ref', 'ref' => 12]`)
 * points at: the one rich section of document 12, of kind "section",
 * published or not. A deleted one is null, so pages that used it draw
 * nothing in its place.
 */
final class CmsSectionResolver implements SectionResolver
{
    public function resolve(int|string $ref): ?array
    {
        if (! ctype_digit((string) $ref)) {
            return null;
        }

        $content = Document::query()->ofKind(Document::SECTION)->whereKey((int) $ref)->value('content');
        $section = PageValue::normalize(is_array($content) ? $content : [])['sections'][0] ?? null;

        return ($section['type'] ?? null) === PageContent::TYPE ? $section : null;
    }
}
