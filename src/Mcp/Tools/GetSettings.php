<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Mcp\SettingsInfo;

#[Name('get_settings')]
#[Description('The site settings (business, contact, hours, social, seo, and any the site adds), each section with its fields and its values (saved values over the defaults), in the shape save_settings takes. Pages show them through dynamic values such as site.phone. A picture (the logo) is a path on the site\'s disk. "editable": false means only the panel can change that section.')]
#[IsReadOnly]
class GetSettings extends Tool
{
    public function handle(SettingsInfo $settings): ResponseFactory
    {
        return Reply::read(['sections' => $settings->full()]);
    }
}
