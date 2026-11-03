<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Vortechron\FilamentCms\Mcp\Contracts\McpEntries;
use Vortechron\FilamentCms\Mcp\Entries\NoEntries;
use Vortechron\FilamentCms\Mcp\Input;
use Vortechron\FilamentCms\Mcp\Reply;

#[Name('delete_entry')]
#[Description('Deletes one entry, like the delete button in the panel. Needs confirm: true. Its old versions are kept.')]
#[IsDestructive]
class DeleteEntry extends Tool
{
    public function handle(Request $request, McpEntries $entries): ResponseFactory
    {
        if (! $entries->available()) {
            return Reply::refuse('entries', NoEntries::MESSAGE);
        }

        $errors = Input::errors($request->all(), ['id' => ['required', 'integer', 'min:1'], 'confirm' => ['required', 'accepted']]);

        if ($errors !== [] || $request->get('confirm') !== true) {
            return Reply::refused($errors ?: ['confirm' => 'Send confirm: true to delete the entry.']);
        }

        $result = $entries->delete((int) $request->get('id'));

        return $result['ok'] ? Reply::saved() : Reply::refused($result['errors'] ?? []);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The entry id.'),
            'confirm' => $schema->boolean()->required()->description('Must be true.'),
        ];
    }
}
