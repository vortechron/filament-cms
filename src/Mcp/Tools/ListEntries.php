<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Vortechron\FilamentCms\Mcp\Contracts\McpEntries;
use Vortechron\FilamentCms\Mcp\Entries\NoEntries;
use Vortechron\FilamentCms\Mcp\Input;
use Vortechron\FilamentCms\Mcp\Reply;

#[Name('list_entries')]
#[Description('The site\'s entries (products, services, posts: whatever entry types the site has), optionally of one type, with their fields, and the entry types with their fields. Change one with save_entry.')]
#[IsReadOnly]
class ListEntries extends Tool
{
    public function handle(Request $request, McpEntries $entries): ResponseFactory
    {
        if (! $entries->available()) {
            return Reply::refuse('entries', NoEntries::MESSAGE);
        }

        $errors = Input::errors($request->all(), [
            'type' => ['nullable', 'string', 'max:60'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        if ($errors !== []) {
            return Reply::refused($errors);
        }

        $types = $entries->types();
        $keys = array_column($types, 'key');

        if ($request->get('type') !== null && ! in_array($request->get('type'), $keys, true)) {
            return Reply::refuse('type', 'There is no entry type '.json_encode($request->get('type')).'. Types: '.implode(', ', $keys).'.');
        }

        return Reply::read([
            'types' => $types,
            'entries' => $entries->list($request->get('type'), (int) ($request->get('limit') ?? 50)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('Only entries of this entry type (its key, such as "product").'),
            'limit' => $schema->integer()->description('At most this many, 1 to 200. Default 50.'),
        ];
    }
}
