<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Vortechron\FilamentCms\Mcp\Documents;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Mcp\SectionEditor;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;

#[Name('list_versions')]
#[Description('The saved versions of a document, newest first (the site keeps the newest 20): id, when, who saved it, whether it is the current value, and an outline of its sections (and an entry\'s fields). Bring one back with restore_version.')]
#[IsReadOnly]
class ListVersions extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $document = Documents::byId($request->get('document_id'), 'document_id');

        if ($document instanceof ResponseFactory) {
            return $document;
        }

        $versions = CmsRevision::query()->with('user')->where('key', $document->revisionKey())->latest('id')->get();

        return Reply::read(['document_id' => $document->getKey(), 'versions' => $versions->map(fn (CmsRevision $version): array => [
            'id' => $version->getKey(),
            'saved_at' => $version->created_at?->toIso8601String(),
            'user' => $version->user === null ? null : ['name' => $version->user->getAttribute('name'), 'email' => $version->user->getAttribute('email')],
            'current' => $version->content === $document->content,
            ...($document->kind === Document::KIT ? [] : ['outline' => SectionEditor::outline(Document::sectionsOf($version->content))]),
            ...($document->kind === Document::ENTRY ? ['fields' => is_array($version->content['fields'] ?? null) ? $version->content['fields'] : []] : []),
        ])->all()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id' => $schema->integer()->required()->description('The document id.'),
        ];
    }
}
