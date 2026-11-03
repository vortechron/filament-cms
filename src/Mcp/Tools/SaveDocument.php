<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Vortechron\FilamentCms\Mcp\Documents;
use Vortechron\FilamentCms\Mcp\DocumentWriter;
use Vortechron\FilamentCms\Mcp\Reply;

#[Name('save_document')]
#[Description('Saves a document\'s whole value (a page value of format 2: read page_format first) and, optionally, its title, path, published flag and SEO words. Everything is checked and cleaned like a save in the panel, kept as a version, and you save as a developer (locks and developer-only data are yours to change). A refused save changes nothing and answers the errors. For one section, prefer update_section. Not for the kit (save_kit) or entries (save_entry).')]
class SaveDocument extends Tool
{
    public function handle(Request $request, DocumentWriter $writer): ResponseFactory
    {
        $document = Documents::byId($request->get('id'), 'id', DocumentWriter::PAGE_KINDS);

        if ($document instanceof ResponseFactory) {
            return $document;
        }

        $value = DocumentWriter::value($request->all());

        if (is_string($value)) {
            return Reply::refuse('value', $value);
        }

        $result = $writer->save($document, $value, $request->all(['title', 'path', 'published', 'seo']));

        return Reply::fromResult($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The document id.'),
            'value' => $schema->object()->description('The whole new value: {"version": 2, "sections": [...]}. Leave it out to change only the title, path, published flag or SEO words.'),
            'title' => $schema->string()->description('The title shown in the panel (and the page title), at most 120 characters.'),
            'path' => $schema->string()->description('Pages only: the address, such as "/tentang". It is cleaned to lowercase words with dashes. "/" is the home page.'),
            'published' => $schema->boolean()->description('Pages only: whether visitors can see the page.'),
            'seo' => $schema->object([
                'title' => $schema->string()->description('At most 70 characters, or null.'),
                'description' => $schema->string()->description('At most 170 characters, or null.'),
            ])->description('Pages only: the search engine title and description.'),
        ];
    }
}
