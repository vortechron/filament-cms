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
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\Mcp\DocumentInfo;
use Vortechron\FilamentCms\Mcp\Input;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Models\Document;

#[Name('list_documents')]
#[Description('Lists the documents (id, kind, title, path, published, updated_at), optionally of one kind. Kinds: page, header, footer, section (synced), template (saved), kit, card, single, entry.')]
#[IsReadOnly]
class ListDocuments extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $errors = Input::errors($request->all(), ['kind' => ['nullable', 'string', Rule::in(CmsKind::values())]], [
            'kind.in' => 'The kind must be one of: '.implode(', ', CmsKind::values()).'.',
        ]);

        if ($errors !== []) {
            return Reply::refused($errors);
        }

        $documents = Document::query()
            ->when($request->get('kind'), fn ($query, string $kind) => $query->ofKind($kind))
            ->orderBy('kind')->orderBy('path')->orderBy('id')
            ->get();

        return Reply::read(['documents' => $documents->map(DocumentInfo::summary(...))->all()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(CmsKind::values())->description('Only documents of this kind. Leave it out for all of them.'),
        ];
    }
}
