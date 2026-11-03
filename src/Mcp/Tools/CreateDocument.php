<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\Mcp\DocumentWriter;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;

#[Name('create_document')]
#[Description('Makes a new document: a page (needs a title and a path; unpublished unless published is true), the header or footer (when the site has none yet), a synced section (kind section) or a saved section (kind template), each of those two holding exactly one rich section. The value is checked and cleaned like a save in the panel and kept as the first version. Answers the new id.')]
class CreateDocument extends Tool
{
    public function handle(Request $request, DocumentWriter $writer): ResponseFactory
    {
        $kind = is_string($request->get('kind')) ? CmsKind::tryFrom($request->get('kind')) : null;

        if ($kind?->mcpCreatable() !== true) {
            return Reply::refuse('kind', 'The kind must be one of: '.implode(', ', self::kinds()).'.');
        }

        $existing = $kind->isPart() ? Document::firstOfKind($kind->value) : null;

        if ($existing !== null) {
            return Reply::refuse('kind', "This site already has a {$kind->value} (document {$existing->getKey()}). Change it with save_document.");
        }

        $value = DocumentWriter::value($request->all());

        if (is_string($value)) {
            return Reply::refuse('value', $value);
        }

        $document = $kind === CmsKind::Page ? new SitePage : new Document(['kind' => $kind->value]);
        $meta = $request->all(['title', 'path', 'published', 'seo']);

        if ($kind->isPart()) {
            $meta['title'] ??= $kind->label();
        }

        $result = $writer->save($document, $value ?? ['version' => PageValue::VERSION, 'sections' => []], $meta);

        return Reply::fromResult($result, fn (): array => ['id' => $document->getKey()]);
    }

    /**
     * @return list<string>
     */
    private static function kinds(): array
    {
        return CmsKind::values(fn (CmsKind $kind): bool => $kind->mcpCreatable());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(self::kinds())->required()->description('page, header, footer, section (synced) or template (saved).'),
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
