<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Vortechron\FilamentCms\Mcp\Contracts\McpFormSubmissions;
use Vortechron\FilamentCms\Mcp\Input;
use Vortechron\FilamentCms\Mcp\Reply;

#[Name('list_form_submissions')]
#[Description('What visitors sent through the site\'s forms, newest first: the form, the page document, the values by field name, the status (new, read, spam) and when. With the count of each status. Filter by status, form_key ({sectionId}.{blockId}) or document_id; page back with before_id. Mark one with update_form_submission.')]
#[IsReadOnly]
class ListFormSubmissions extends Tool
{
    public function handle(Request $request, McpFormSubmissions $submissions): ResponseFactory
    {
        $errors = Input::errors($request->all(), [
            'status' => ['nullable', 'string', Rule::in(McpFormSubmissions::STATUSES)],
            'form_key' => ['nullable', 'string', 'max:100'],
            'document_id' => ['nullable', 'integer', 'min:1'],
            'before_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], ['status.in' => 'The status is new, read or spam.']);

        return $errors === []
            ? Reply::read($submissions->list($request->all(['status', 'form_key', 'document_id', 'before_id', 'limit'])))
            : Reply::refused($errors);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(McpFormSubmissions::STATUSES)->description('Only submissions with this status.'),
            'form_key' => $schema->string()->description('Only one form: its key, {sectionId}.{blockId}.'),
            'document_id' => $schema->integer()->description('Only forms on this document.'),
            'before_id' => $schema->integer()->description('Only submissions older than this id, to read further back.'),
            'limit' => $schema->integer()->description('At most this many, 1 to 100. Default 20.'),
        ];
    }
}
