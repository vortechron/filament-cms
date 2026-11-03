<?php

namespace Vortechron\FilamentCms;

use Illuminate\Contracts\View\View;
use Vortechron\FilamentBlockEditor\Content\PageContent;
use Vortechron\FilamentBlockEditor\Rendering\PageRenderer;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Forms\CmsFormEndpoint;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;

/**
 * Draws a site page inside the project's own page view
 * (config filament-cms.page_view), for visitors and for the builder's
 * live preview. The view gets:
 *
 * - `$page`, `$sections`, `$content` (the sections as HTML), `$context`, `$isBuilderPreview`;
 * - `$header` and `$footer`: the header and footer documents as HTML, each in
 *   `<div data-part="header|footer" class="sf-page">`, or '' when there is none
 *   or it has no visible section.
 */
final class SitePageRenderer
{
    public function __construct(private readonly PageRenderer $pages) {}

    /**
     * A page for visitors, or its builder preview with unsaved sections. In
     * the preview the header and footer are drawn as visitors see them.
     *
     * @param  list<array<string, mixed>>|null  $sections  unsaved sections for a preview
     */
    public function view(SitePage $page, ?array $sections = null, bool $forBuilder = false): View
    {
        $values = self::pageValues($page);
        $context = new RenderContext(forBuilder: $forBuilder, isPreview: $forBuilder, values: $values);
        $sections ??= $page->sections;

        return $this->pageView($page, $sections, $context, [
            'content' => $this->pages->render($sections, $context),
            'header' => $this->part(Document::HEADER, $values),
            'footer' => $this->part(Document::FOOTER, $values),
        ]);
    }

    /**
     * The builder preview of a document that is not a page, inside the page
     * view of the home page. A header or footer is drawn in its place, with
     * the home page's content (in `<div data-part="page">`) so it looks
     * real; a synced section, card or single is drawn as the content.
     *
     * @param  list<array<string, mixed>>  $sections  the unsaved sections
     * @param  array<string, mixed>  $values  more dynamic values, such as an entry's for a card
     */
    public function preview(Document $document, array $sections, array $values = []): View
    {
        $home = SitePage::home() ?? new SitePage(['title' => $document->title, 'path' => '/']);
        $values = [...self::pageValues($home), ...$values];
        $context = new RenderContext(forBuilder: true, isPreview: true, values: $values);
        $edited = '<div class="sf-page" data-preview>'.$this->pages->render($sections, $context).'</div>';
        $isPart = $document->cmsKind()?->isPart() === true;

        return $this->pageView($home, $isPart ? $home->sections : $sections, $context, [
            'content' => $isPart ? self::wrap('page', $this->pages->render($home->sections, new RenderContext(values: $values))) : $this->pages->render($sections, $context),
            'header' => $document->kind === Document::HEADER ? $edited : $this->part(Document::HEADER, $values),
            'footer' => $document->kind === Document::FOOTER ? $edited : $this->part(Document::FOOTER, $values),
        ]);
    }

    /**
     * The header or footer as visitors see it, wrapped, or '' when there is
     * none or it has no visible section.
     *
     * @param  array<string, mixed>  $values  dynamic values of the page it is drawn on
     */
    public function part(string $kind, array $values = []): string
    {
        $sections = Document::firstOfKind($kind)?->contentSections() ?? [];

        if (PageContent::visible($sections) === []) {
            return '';
        }

        return self::wrap($kind, $this->pages->render($sections, new RenderContext(values: $values)));
    }

    /**
     * A document with an address (a page, an entry) drawn as `$content`
     * inside the page view, with the header and footer around it.
     *
     * @param  list<array<string, mixed>>  $sections
     */
    public function layout(Document $page, array $sections, RenderContext $context, string $content): View
    {
        return $this->pageView($page, $sections, $context, [
            'content' => $content,
            'header' => $this->part(Document::HEADER, $context->values),
            'footer' => $this->part(Document::FOOTER, $context->values),
        ]);
    }

    /**
     * The page.* dynamic values of a page (or an entry drawn as a page), and
     * `document.id` so its forms post to it (Forms\CmsFormEndpoint).
     *
     * @return array<string, string|null>
     */
    public static function pageValues(Document $page): array
    {
        return [
            'page.title' => (string) $page->title,
            'page.url' => $page->url(),
            ...($page->exists ? [CmsFormEndpoint::VALUE => (string) $page->getKey()] : []),
        ];
    }

    /**
     * The page.* values of the home page (an empty one before it is made):
     * what a document with no page of its own (a section, a part) is drawn with.
     *
     * @return array<string, string|null>
     */
    public static function homeValues(): array
    {
        return self::pageValues(SitePage::home() ?? new SitePage(['title' => '', 'path' => '/']));
    }

    private static function wrap(string $part, string $html): string
    {
        return '<div data-part="'.e($part).'" class="sf-page">'.$html.'</div>';
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  array{content: string, header: string, footer: string}  $html
     */
    private function pageView(Document $page, array $sections, RenderContext $context, array $html): View
    {
        return view(config('filament-cms.page_view', 'cms.page'), [
            'page' => $page,
            'sections' => $sections,
            'context' => $context,
            ...$html,
            'isBuilderPreview' => $context->forBuilder,
        ]);
    }
}
