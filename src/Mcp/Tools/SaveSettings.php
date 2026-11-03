<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Vortechron\FilamentCms\Mcp\Input;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Mcp\SettingsInfo;
use Vortechron\FilamentCms\Mcp\SettingsWriter;

#[Name('save_settings')]
#[Description('Changes the site settings, section by section, such as {"contact": {"email": "kedai@example.com", "phones": ["012-345 6789"]}, "hours": {"note": "Tutup Ahad"}}. Only the fields you send change; every other value is kept. Values are checked and cleaned like the shop info form in the builder and kept as a version. A new picture must be a JPG, PNG, WebP or GIF already uploaded to the site, given by its path or address. Any refused value refuses the whole save. Answers the sections like get_settings.')]
class SaveSettings extends Tool
{
    public function handle(Request $request, SettingsWriter $writer, SettingsInfo $info): ResponseFactory
    {
        $settings = Input::json($request->get('settings'));
        $result = $writer->save(is_array($settings) ? $settings : []);

        return Reply::fromResult($result, fn (): array => ['sections' => $info->full()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'settings' => $schema->object()->required()->description('By section key (business, contact, hours, social, ...), the fields to change and their new values. get_settings shows the fields.'),
        ];
    }
}
