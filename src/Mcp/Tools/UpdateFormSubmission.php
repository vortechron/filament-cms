<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Vortechron\FilamentCms\Mcp\Contracts\McpFormSubmissions;
use Vortechron\FilamentCms\Mcp\Forms\NoFormSubmissions;
use Vortechron\FilamentCms\Mcp\Input;
use Vortechron\FilamentCms\Mcp\Reply;

#[Name('update_form_submission')]
#[Description('Marks a form submission as new, read or spam, like the Borang screen in the panel.')]
class UpdateFormSubmission extends Tool
{
    public function handle(Request $request, McpFormSubmissions $submissions): ResponseFactory
    {
        if (! $submissions->available()) {
            return Reply::refuse('forms', NoFormSubmissions::MESSAGE);
        }

        $errors = Input::errors($request->all(), [
            'id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', Rule::in(McpFormSubmissions::STATUSES)],
        ], ['status.in' => 'The status is new, read or spam.']);

        if ($errors !== []) {
            return Reply::refused($errors);
        }

        $result = $submissions->update((int) $request->get('id'), (string) $request->get('status'));

        return $result['ok'] ? Reply::saved() : Reply::refused($result['errors'] ?? []);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The submission id.'),
            'status' => $schema->string()->enum(McpFormSubmissions::STATUSES)->required()->description('new, read or spam.'),
        ];
    }
}
