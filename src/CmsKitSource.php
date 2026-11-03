<?php

namespace Vortechron\FilamentCms;

use Vortechron\FilamentBlockEditor\Design\KitSource;
use Vortechron\FilamentBlockEditor\Design\OverridesKit;
use Vortechron\FilamentCms\Models\Document;

/**
 * The site's design kit: the one document of kind "kit", cleaned and
 * merged over Kit::defaults(). Bound as the block editor's KitSource, so
 * `<x-filament-block-editor::styles />` prints it. Read it with cms_kit().
 */
final class CmsKitSource implements KitSource
{
    use OverridesKit;

    protected function storedKit(): array
    {
        $content = Document::firstOfKind(Document::KIT)?->content;

        return is_array($content) ? $content : [];
    }
}
