<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentCms\Mcp\DocumentWriter;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;

#[Name('create_document')]
#[Description('Makes a new document: a page (needs a title and a path; unpublished unless published is true), the header or footer (when the site has none yet), a synced section (kind section) or a saved section (kind template), each of those two holding exactly one rich section. The value is checked and cleaned like a save in the panel and kept as the first version. Answers the new id.')]
class CreateDocument extends Tool
{
    public const KINDS = [Document::PAGE, Document::HEADER, Document::FOOTER, Document::SECTION, Document::TEMPLATE];

    public function handle(Request $request, DocumentWriter $writer): ResponseFactory
    {
        $kind = $request->get('kind');

        if (! in_array($kind, self::KINDS, true)) {
            return Reply::refuse('kind', 'The kind must be one of: '.implode(', ', self::KINDS).'.');
        }

        $existing = in_array($kind, Document::PARTS, true) ? Document::firstOfKind($kind) : null;

        if ($existing !== null) {
            return Reply::refuse('kind', "This site already has a {$kind} (document {$existing->getKey()}). Change it with save_document.");
        }

        $value = DocumentWriter::value($request->all());

        if (is_string($value)) {
            return Reply::refuse('value', $value);
        }

        $document = $kind === Document::PAGE ? new SitePage : new Document(['kind' => $kind]);
        $meta = $request->all(['title', 'path', 'published', 'seo']);

        if (in_array($kind, Document::PARTS, true)) {
            $meta['title'] ??= __("filament-cms::documents.{$kind}");
        }

        $result = $writer->save($document, $value ?? ['version' => PageValue::VERSION, 'sections' => []], $meta);

        return $result['ok']
            ? Reply::saved(['id' => $document->getKey(), ...array_diff_key($result, ['ok' => true])])
            : Reply::refused($result['errors'] ?? []);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(self::KINDS)->required()->description('page, header, footer, section (synced) or template (saved).'),
            'title' => $schema->string()->description('The title, at most 120 characters. Needed except for the header and footer.'),
            'path' => $schema->string()->description('Pages only, and needed for them: the address, such as "/tentang".'),
            'published' => $schema->boolean()->description('Pages only. Default false.'),
            'value' => $schema->object()->description('The page value: {"version": 2, "sections": [...]} (see page_format). Default: no sections.'),
            'seo' => $schema->object([
                'title' => $schema->string(),
                'description' => $schema->string(),
            ])->description('Pages only: the search engine title (70) and description (170).'),
        ];
    }
}
