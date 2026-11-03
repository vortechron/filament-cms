<?php

namespace Vortechron\FilamentCms\Mcp;

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
}
