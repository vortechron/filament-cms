<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Vortechron\FilamentBlockEditor\Design\Kit;
use Vortechron\FilamentBlockEditor\Design\KitSource;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Models\Document;

#[Name('get_kit')]
#[Description('The site\'s design kit (colours, custom colours, fonts, text, corners, buttons, forms) as every page draws it, the site\'s defaults, and the fonts a kit or a style may use. Styles use the colours as kit:<key>.')]
#[IsReadOnly]
class GetKit extends Tool
{
    public function handle(KitSource $source): ResponseFactory
    {
        return Reply::read([
            'kit' => $source->current(),
            'defaults' => Kit::defaults(),
            'fonts' => array_map(
                fn (string $key, array $font): array => ['key' => $key, 'label' => $font['label']],
                array_keys(Kit::fonts()),
                Kit::fonts(),
            ),
            'document_id' => Document::firstOfKind(Document::KIT)?->getKey(),
        ]);
    }
}
