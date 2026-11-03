<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Mcp\Site;

#[Name('site_overview')]
#[Description('Start here. The site\'s name and address, every document (pages, header, footer, synced and saved sections, card and entry page designs), the blocks you can use with their fields, the dynamic values, the design kit, the settings sections, the entry types and the page format version.')]
#[IsReadOnly]
class SiteOverview extends Tool
{
    public function handle(): ResponseFactory
    {
        return Reply::read([
            'name' => Site::name(),
            'url' => Site::url(),
        ]);
    }
}
