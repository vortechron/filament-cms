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
use Vortechron\FilamentBlockEditor\Rendering\PageRenderer;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\Entries\EntryBuilder;
use Vortechron\FilamentCms\Entries\EntryList;
use Vortechron\FilamentCms\Entries\EntryPageRenderer;
use Vortechron\FilamentCms\Mcp\Documents;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SitePageRenderer;

#[Name('render_document')]
#[Description('The HTML visitors get, as text, to check your changes: a page or an entry in the site\'s own layout (with the header and footer; an entry is its type\'s entry page design with its values, then its own sections), or the header or footer alone. Only what visitors can see is drawn unless include_unpublished is true: then an unpublished page or entry, a synced or saved section, or a card or entry page design is drawn too. A section is drawn alone; a card or entry page design with the values of the type\'s first entry. The HTML is cut at about 200 KB, with a note on the first line when it is.')]
#[IsReadOnly]
class RenderDocument extends Tool
{
    public const MAX_BYTES = 200_000;

    public function handle(Request $request, SitePageRenderer $site, EntryPageRenderer $entries, PageRenderer $pages): Response|ResponseFactory
    {
        $document = Documents::find($request->all(['id', 'path']));

        if (is_array($document)) {
            return Reply::refused($document);
        }

        if ($document->cmsKind()?->holdsSections() !== true) {
            return Reply::refuse('id', 'The design kit is not drawn on its own. Read it with get_kit.');
        }

        if (! self::isPublic($document) && $request->get('include_unpublished') !== true) {
            return Reply::refuse('include_unpublished', "Visitors do not see document {$document->getKey()} ({$document->kind}) on its own. Send include_unpublished: true to draw it anyway.");
        }

        return Response::text(self::cut(match (true) {
            $document->kind === Document::PAGE => $site->view(SitePage::query()->findOrFail($document->getKey()))->render(),
            $document->kind === Document::ENTRY => $entries->view(Entry::query()->findOrFail($document->getKey()))->render(),
            $document->cmsKind()->isPart() => $site->part($document->kind, SitePageRenderer::homeValues()),
            default => self::sections($document, $pages),
        }));
    }

    /**
     * A section or a design on its own. A card is drawn as the list block
     * draws it, and a single as the top of an entry's page, each with the
     * values of the type's first entry.
     */
    private static function sections(Document $document, PageRenderer $pages): string
    {
        $page = EntryBuilder::page($document);
        $pageValues = $page === null ? SitePageRenderer::homeValues() : SitePageRenderer::pageValues($page);
        $isCard = $document->kind === Document::CARD;
        $values = $isCard ? EntryList::cardValues($pageValues, EntryBuilder::entryValues($document)) : [...$pageValues, ...EntryBuilder::entryValues($document)];
        $draw = fn (): string => $pages->render($document->contentSections(), new RenderContext(values: $values));

        return '<div class="sf-page">'.($isCard ? EntryList::drawingCard($draw) : $draw()).'</div>';
    }

    /**
     * Pages and entries visitors can open, and the header and footer every
     * page draws.
     */
    private static function isPublic(Document $document): bool
    {
        $kind = $document->cmsKind();

        if ($kind?->isPart() === true) {
            return true;
        }

        // An entry has a page only while its type is registered with a path.
        return $kind?->isRoutable() === true && $document->is_published
            && ($kind !== CmsKind::Entry || EntryBuilder::type($document)?->getPath() !== null);
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
            'path' => $schema->string()->description('Instead of the id: the path of a page or entry, such as "/tentang" or "/produk/kopi".'),
            'include_unpublished' => $schema->boolean()->description('Draw it even when visitors cannot see it (an unpublished page or entry, a section, a design). Default false.'),
        ];
    }
}
