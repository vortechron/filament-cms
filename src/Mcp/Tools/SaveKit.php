<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Vortechron\FilamentCms\Mcp\Input;
use Vortechron\FilamentCms\Mcp\KitWriter;
use Vortechron\FilamentCms\Mcp\Reply;

#[Name('save_kit')]
#[Description('Changes the site\'s design kit; every page follows it at once. Send only the parts you change, such as {"colors": {"primary": "#c59d6e"}, "buttons": {"style": "pill"}}: they are merged over the stored kit. A sent custom_colors replaces the whole list. A value the kit does not allow refuses the whole save and changes nothing. Kept as a version. The grammar is in page_format (Design kit).')]
class SaveKit extends Tool
{
    public function handle(Request $request, KitWriter $writer): ResponseFactory
    {
        $kit = Input::json($request->get('kit'));

        if (! is_array($kit) || ($kit !== [] && array_is_list($kit))) {
            return Reply::refuse('kit', 'Send the kit parts to change as an object, such as {"colors": {"primary": "#c59d6e"}}.');
        }

        $result = $writer->save($kit);

        return Reply::fromResult($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kit' => $schema->object()->required()->description('The kit parts to change: colors, custom_colors, fonts, text, radius, buttons, forms.'),
        ];
    }
}
