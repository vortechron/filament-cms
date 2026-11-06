<?php

namespace Vortechron\FilamentCms;

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

        $synced = Document::query()->ofKind(Document::SECTION)->find((int) $ref);

        if ($synced !== null && SiteLanguages::enabled() && $synced->language() !== SiteLanguages::current()) {
            $synced = $synced->translation(SiteLanguages::current());
        }

        return $synced === null ? null : SectionLibrary::firstSection($synced);
    }
}
