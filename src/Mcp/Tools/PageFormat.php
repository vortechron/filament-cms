<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Vortechron\FilamentCms\Mcp\FormatGuide;

#[Name('page_format')]
#[Description('Read this before you write a page value. Page format 2 as Markdown: document kinds, sections, synced sections (ref), locks, the style bag keys and value grammar, advanced settings, the BlockNote block shapes (heading, paragraph, image, button, own block, form, ...) with the look prop, dynamic values in text, links and pictures (and `{key}` short codes inside own block text fields), the form JSON, the design kit, and the limits. Generated from the site\'s own code, so it matches what the store keeps.')]
#[IsReadOnly]
class PageFormat extends Tool
{
    public function handle(FormatGuide $guide): Response
    {
        return Response::text($guide->text());
    }
}
