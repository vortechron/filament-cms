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
use Vortechron\FilamentCms\Mcp\SectionEditor;
use Vortechron\FilamentCms\Models\Document;

#[Name('move_section')]
#[Description('Moves one section of a document to a new index (0 is the top; an index past the end moves it to the end) and saves the document through the store. Answers the sections in order.')]
class MoveSection extends Tool
{
    public function handle(Request $request, SectionEditor $editor): ResponseFactory
    {
        $errors = Input::errors($request->all(), ['index' => ['required', 'integer', 'min:0']]);

        if ($errors !== []) {
            return Reply::refused($errors);
        }

        $sectionId = $request->get('section_id');

        return $editor->edit($request->get('document_id'), function (array $sections, Document $document) use ($sectionId, $request): array|ResponseFactory {
            $index = SectionEditor::find($document, $sections, $sectionId);

            if ($index instanceof ResponseFactory) {
                return $index;
            }

            $section = array_splice($sections, $index, 1)[0];
            array_splice($sections, min((int) $request->get('index'), count($sections)), 0, [$section]);

            return $sections;
        }, ['section_id' => $sectionId]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id' => $schema->integer()->required()->description('The document id.'),
            'section_id' => $schema->string()->required()->description('The id of the section to move.'),
            'index' => $schema->integer()->required()->description('Its new place, from 0 (the top).'),
        ];
    }
}
