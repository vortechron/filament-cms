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

#[Name('update_section')]
#[Description('Changes one section of a document, found by its id, and saves the document through the store (checked, cleaned, kept as a version). The keys you send replace the stored ones, and "data" is merged key by key, so {"data": {"padding": "lg"}} changes only the padding and {"data": {"document": [...]}} replaces only the words. Send "hidden": false to show it again, "lock": {} to remove its locks. Answers the document\'s sections in order.')]
class UpdateSection extends Tool
{
    public function handle(Request $request, SectionEditor $editor): ResponseFactory
    {
        $given = Input::json($request->get('section'));

        if (! is_array($given) || array_is_list($given)) {
            return Reply::refuse('section', 'Send the section keys to change as an object, such as {"data": {"padding": "lg"}}.');
        }

        $sectionId = $request->get('section_id');

        return $editor->edit($request->get('document_id'), function (array $sections, Document $document) use ($given, $sectionId): array|ResponseFactory {
            $index = SectionEditor::find($document, $sections, $sectionId);

            if ($index instanceof ResponseFactory) {
                return $index;
            }

            $stored = $sections[$index];
            $data = is_array($given['data'] ?? null) ? [...($stored['data'] ?? []), ...$given['data']] : ($stored['data'] ?? null);
            $sections[$index] = array_filter([...$stored, ...$given, 'id' => $stored['id'], 'data' => $data], fn (mixed $value): bool => $value !== null);

            return $sections;
        }, ['section_id' => $sectionId], $request->get('expected_version'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'expected_version' => $schema->integer()->description('Required for shared documents: edit_version from get_document.'),
            'document_id' => $schema->integer()->required()->description('The document id.'),
            'section_id' => $schema->string()->required()->description('The id of the section to change.'),
            'section' => $schema->object()->required()->description('The section keys to change: type, ref, hidden, lock, data (merged key by key). See page_format.'),
        ];
    }
}
