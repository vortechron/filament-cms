<?php

namespace Vortechron\FilamentCms\Entries;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Vortechron\FilamentBlockEditor\Content\PageContent;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentBlockEditor\Rendering\PageRenderer;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\SitePageRenderer;

/**
 * An entry's own page, in the project's page view (`$page` is the entry):
 * the type's single design drawn with the entry's values, or, when there is
 * none, a plain layout of its fields (`filament-cms::entries.page`); then
 * the entry's body. The SEO title is the entry's title, the description its
 * first textarea.
 */
final class EntryPageRenderer
{
    public function __construct(private readonly PageRenderer $pages, private readonly SitePageRenderer $site) {}

    /**
     * For visitors, or the builder preview of its body with unsaved sections.
     *
     * @param  list<array<string, mixed>>|null  $sections
     */
    public function view(Entry $entry, ?array $sections = null, bool $forBuilder = false): View
    {
        $type = $entry->definition();
        $values = [...SitePageRenderer::pageValues($entry), ...EntryValues::of($entry)];
        $context = new RenderContext(forBuilder: $forBuilder, isPreview: $forBuilder, values: $values);
        $sections ??= $type?->hasBody() ? PageValue::normalize($entry->content ?? [])['sections'] : [];

        return $this->site->layout($this->withSeo($entry, $type), $sections, $context, $this->top($entry, $type, $values, $forBuilder).$this->pages->render($sections, $context));
    }

    /**
     * The single design as visitors see it, or the plain layout.
     *
     * @param  array<string, mixed>  $values
     */
    private function top(Entry $entry, ?EntryType $type, array $values, bool $forBuilder): string
    {
        $single = $type === null ? null : EntryTemplates::find($type, Document::SINGLE);
        $sections = PageContent::visible(PageValue::normalize($single?->content ?? [])['sections']);

        if ($sections === []) {
            return view('filament-cms::entries.page', ['entry' => $entry, 'type' => $type, 'values' => $values])->render();
        }

        $html = $this->pages->render($sections, new RenderContext(values: $values));

        return $forBuilder ? '<div data-part="page" class="sf-page">'.Inert::html($html).'</div>' : $html;
    }

    /**
     * A copy of the entry with its SEO title and description, for the view.
     */
    private function withSeo(Entry $entry, ?EntryType $type): Entry
    {
        $field = $type?->firstField('textarea');
        $text = $field === null ? null : ($entry->fieldValues()[$field->name] ?? null);

        return (clone $entry)->forceFill([
            'seo_title' => $entry->title,
            'seo_description' => is_string($text) && trim($text) !== '' ? Str::limit(trim((string) preg_replace('/\s+/', ' ', $text)), 160) : null,
        ]);
    }
}
