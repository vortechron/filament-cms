<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Vortechron\FilamentCms\Mcp\Contracts\McpEntries;
use Vortechron\FilamentCms\Mcp\Entries\NoEntries;
use Vortechron\FilamentCms\Mcp\Input;
use Vortechron\FilamentCms\Mcp\McpActor;
use Vortechron\FilamentCms\Mcp\Reply;

#[Name('save_entry')]
#[Description('Makes a new entry (leave out the id) or changes one. Fields are checked and cleaned by the entry type\'s fields (list_entries shows them); sections, when the type has a body, are a list of page sections (see page_format). Keys you leave out keep their stored value. Kept as a version. Answers the id and the entry\'s address.')]
class SaveEntry extends Tool
{
    public function handle(Request $request, McpEntries $entries): ResponseFactory
    {
        if (! $entries->available()) {
            return Reply::refuse('entries', NoEntries::MESSAGE);
        }

        $entry = array_filter([
            ...$request->all(['id', 'type', 'title', 'slug', 'published', 'sort']),
            'fields' => Input::json($request->get('fields')),
            'sections' => Input::json($request->get('sections')),
        ], fn (mixed $value): bool => $value !== null);

        $errors = Input::errors([...$request->all(), ...$entry], [
            'id' => ['nullable', 'integer', 'min:1'],
            'type' => ['required_without:id', 'string', 'max:60'],
            'title' => ['required_without:id', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'published' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'fields' => ['nullable', 'array'],
            'sections' => ['nullable', 'array'],
        ]);

        if ($errors !== []) {
            return Reply::refused($errors);
        }

        $result = $entries->save($entry, McpActor::user());

        return Reply::fromResult($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The entry to change. Leave it out to make a new one.'),
            'type' => $schema->string()->description('The entry type key, such as "product". Needed for a new entry.'),
            'title' => $schema->string()->description('At most 120 characters. Needed for a new entry.'),
            'slug' => $schema->string()->description('The last part of its address; made from the title when left out.'),
            'published' => $schema->boolean()->description('Whether visitors see it.'),
            'sort' => $schema->integer()->description('Its place, for types sorted by hand.'),
            'fields' => $schema->object()->description('The entry type\'s field values, by field name.'),
            'sections' => $schema->array()->description('Its own page sections, when the type has a body.'),
        ];
    }
}
