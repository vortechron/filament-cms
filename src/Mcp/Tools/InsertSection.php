<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Vortechron\FilamentBlockEditor\Content\PageContent;
use Vortechron\FilamentCms\Mcp\Input;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Mcp\SectionEditor;

#[Name('insert_section')]
#[Description('Adds one section to a document at an index (0 is the top; leave it out, or give one past the end, to add it at the end) and saves the document through the store. A section without an id gets a new one. A synced section ({"type": "ref", "ref": <id of a section document>}) is a section too. Answers the new section\'s id and the sections in order.')]
class InsertSection extends Tool
{
    public function handle(Request $request, SectionEditor $editor): ResponseFactory
    {
        $section = Input::json($request->get('section'));
        $errors = Input::errors(['section' => $section, 'index' => $request->get('index')], [
            'section' => ['required', 'array'],
            'index' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($errors !== [] || array_is_list($section)) {
            return Reply::refused($errors ?: ['section' => 'Send one section as an object (see page_format).']);
        }

        $id = is_string($section['id'] ?? null) && $section['id'] !== '' ? $section['id'] : PageContent::newId();

        return $editor->edit($request->get('document_id'), function (array $sections) use ($section, $id, $request): array|ResponseFactory {
            if (in_array($id, array_column($sections, 'id'), true)) {
                return Reply::refuse('section.id', "A section with the id {$id} is already in this document. Leave the id out to get a new one.");
            }

            $index = min((int) ($request->get('index') ?? count($sections)), count($sections));
            array_splice($sections, $index, 0, [[...$section, 'id' => $id]]);

            return $sections;
        }, ['section_id' => $id], $request->get('expected_version'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'expected_version' => $schema->integer()->description('Required for shared documents: edit_version from get_document.'),
            'document_id' => $schema->integer()->required()->description('The document id.'),
            'section' => $schema->object()->required()->description('The new section: {"type": "rich", "data": {...}} or {"type": "ref", "ref": 12}. See page_format.'),
            'index' => $schema->integer()->description('Where it goes, from 0 (the top). Default: the end.'),
        ];
    }
}
