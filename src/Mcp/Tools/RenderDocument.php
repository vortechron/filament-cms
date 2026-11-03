<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentBlockEditor\Rendering\PageRenderer;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Mcp\Documents;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SitePageRenderer;

#[Name('render_document')]
#[Description('The HTML visitors get, as text, to check your changes: a page in the site\'s own layout (with the header and footer), or the header or footer alone. Only what visitors can see is drawn unless include_unpublished is true: then an unpublished page, a synced or saved section, a card or entry page design, or an entry is drawn too (its sections only). The HTML is cut at about 200 KB, with a note on the first line when it is.')]
#[IsReadOnly]
class RenderDocument extends Tool
{
    public const MAX_BYTES = 200_000;

    public function handle(Request $request, SitePageRenderer $site, PageRenderer $pages): Response|ResponseFactory
    {
        $document = Documents::find($request->all(['id', 'path']));

        if (is_array($document)) {
            return Reply::refused($document);
        }

        if ($document->kind === Document::KIT) {
            return Reply::refuse('id', 'The design kit is not drawn on its own. Read it with get_kit.');
        }

        if (! self::isPublic($document) && $request->get('include_unpublished') !== true) {
            return Reply::refuse('include_unpublished', "Visitors do not see document {$document->getKey()} ({$document->kind}) on its own. Send include_unpublished: true to draw it anyway.");
        }

        return Response::text(self::cut(match ($document->kind) {
            Document::PAGE => $site->view(SitePage::query()->findOrFail($document->getKey()))->render(),
            Document::HEADER, Document::FOOTER => $site->part($document->kind, self::homeValues()),
            default => '<div class="sf-page">'.$pages->render(PageValue::normalize($document->content ?? [])['sections'], new RenderContext(values: self::homeValues())).'</div>',
        }));
    }

    /**
     * Pages and entries visitors can open, and the header and footer every
     * page draws.
     */
    private static function isPublic(Document $document): bool
    {
        return in_array($document->kind, Document::PARTS, true)
            || (in_array($document->kind, [Document::PAGE, Document::ENTRY], true) && $document->is_published);
    }

    /**
     * @return array<string, string>
     */
    private static function homeValues(): array
    {
        return SitePageRenderer::pageValues(SitePage::query()->where('path', '/')->first() ?? new SitePage(['title' => '', 'path' => '/']));
    }

    private static function cut(string $html): string
    {
        if (strlen($html) <= self::MAX_BYTES) {
            return $html;
        }

        return '<!-- render_document: the HTML is '.strlen($html).' bytes, cut to the first '.self::MAX_BYTES.'. -->'."\n"
            .mb_strcut($html, 0, self::MAX_BYTES, 'UTF-8');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The document id.'),
            'path' => $schema->string()->description('Instead of the id: the path of a page, such as "/tentang".'),
            'include_unpublished' => $schema->boolean()->description('Draw it even when visitors cannot see it (an unpublished page, a section, a design). Default false.'),
        ];
    }
}
