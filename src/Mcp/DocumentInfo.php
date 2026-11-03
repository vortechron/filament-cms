<?php

namespace Vortechron\FilamentCms\Mcp;

use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentBlockEditor\Design\Kit;
use Vortechron\FilamentCms\Models\Document;

/**
 * How the MCP server shows a document to the agent.
 */
final class DocumentInfo
{
    /**
     * One row of a list: id, kind, title, path, published.
     *
     * @return array<string, mixed>
     */
    public static function summary(Document $document): array
    {
        return [
            'id' => $document->getKey(),
            'kind' => $document->kind,
            'title' => (string) $document->title,
            'path' => $document->path,
            'published' => (bool) $document->is_published,
            ...($document->entry_type === null ? [] : ['entry_type' => $document->entry_type]),
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Everything about one document: its meta, its address, and its value
     * as the store keeps it.
     *
     * @return array<string, mixed>
     */
    public static function full(Document $document): array
    {
        return [
            ...self::summary($document),
            'url' => $document->path === null ? null : $document->url(),
            'seo' => ['title' => $document->getAttribute('seo_title'), 'description' => $document->getAttribute('seo_description')],
            'value' => self::value($document),
        ];
    }

    /**
     * The stored value, upgraded and cleaned: a page value, or the kit. An
     * entry keeps its fields next to its sections.
     *
     * @return array<string, mixed>
     */
    public static function value(Document $document): array
    {
        $content = is_array($document->content) ? $document->content : [];

        return match ($document->kind) {
            Document::KIT => Kit::normalize($content),
            Document::ENTRY => [...PageValue::upgrade($content), 'sections' => PageValue::normalize($content)['sections']],
            default => PageValue::normalize($content),
        };
    }
}
