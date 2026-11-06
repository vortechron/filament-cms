<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Vortechron\FilamentCms\Mcp\SectionEditor;
use Vortechron\FilamentCms\Models\Document;

#[Name('remove_section')]
#[Description('Takes one section out of a document and saves the document through the store. The old value stays in the versions (list_versions, restore_version). To hide a section without removing it, use update_section with {"hidden": true}. Answers the sections in order.')]
class RemoveSection extends Tool
{
    public function handle(Request $request, SectionEditor $editor): ResponseFactory
    {
        $sectionId = $request->get('section_id');

        return $editor->edit($request->get('document_id'), function (array $sections, Document $document) use ($sectionId): array|ResponseFactory {
            $index = SectionEditor::find($document, $sections, $sectionId);

            if ($index instanceof ResponseFactory) {
                return $index;
            }

            array_splice($sections, $index, 1);

            return $sections;
        }, ['section_id' => $sectionId], $request->get('expected_version'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'expected_version' => $schema->integer()->description('Required for shared documents: edit_version from get_document.'),
            'document_id' => $schema->integer()->required()->description('The document id.'),
            'section_id' => $schema->string()->required()->description('The id of the section to remove.'),
        ];
    }
}
