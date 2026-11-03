<?php

namespace Vortechron\FilamentCms;

use Vortechron\FilamentCms\Models\SitePage;

/**
 * How many pages the client may have, from `filament-cms.max_pages` (the
 * package they bought, such as 5 for Website Starter). Every page counts,
 * published or not, the home page too; the header, footer and sections do
 * not. The panel's "new page" button and the MCP create_document tool stop
 * at the limit. Existing pages are never touched.
 */
final class PageLimit
{
    /**
     * The most pages, or null for no limit (no setting, 0, or not a whole
     * number).
     */
    public static function max(): ?int
    {
        $max = filter_var(config('filament-cms.max_pages'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $max === false ? null : $max;
    }

    public static function used(): int
    {
        return SitePage::query()->count();
    }

    public static function reached(): bool
    {
        $max = self::max();

        return $max !== null && self::used() >= $max;
    }
}
