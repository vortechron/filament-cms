<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Vortechron\FilamentCms\Mcp\DocumentInfo;
use Vortechron\FilamentCms\Mcp\Documents;
use Vortechron\FilamentCms\Mcp\Reply;

#[Name('get_document')]
#[Description('One document by id, or a page or entry by its path: id, kind, title, path, url, published, seo and its value as the store keeps it (a page value of format 2, or the kit for kind kit). Change it with save_document or the section tools.')]
#[IsReadOnly]
class GetDocument extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $document = Documents::find($request->all());

        return is_array($document) ? Reply::refused($document) : Reply::read(DocumentInfo::full($document));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The document id (from site_overview or list_documents).'),
            'path' => $schema->string()->description('Instead of the id: the path of a page or entry, such as "/" or "/tentang".'),
        ];
    }
}
