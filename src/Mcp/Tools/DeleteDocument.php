<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\Mcp\DocumentInfo;
use Vortechron\FilamentCms\Mcp\Documents;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\SectionLibrary;

#[Name('delete_document')]
#[Description('Deletes a page, a synced or saved section, or a card or entry page design, like the delete button in the panel. Needs confirm: true. Never the home page, the header, the footer or the kit; entries go with delete_entry. Deleting a synced section answers the documents that showed it: they draw nothing in its place from then on. Its old versions are kept.')]
#[IsDestructive]
class DeleteDocument extends Tool
{
    public function handle(Request $request, SectionLibrary $sections): ResponseFactory
    {
        $document = Documents::byId($request->get('id'), 'id');

        if ($document instanceof ResponseFactory) {
            return $document;
        }

        $refusal = self::refusal($document);

        if ($refusal !== null) {
            return Reply::refuse('id', $refusal);
        }

        if ($request->get('confirm') !== true) {
            return Reply::refuse('confirm', 'Send confirm: true to delete "'.$document->title.'". It cannot be undone here.');
        }

        $usedBy = $document->kind === Document::SECTION ? $sections->usedBy($document)->map(DocumentInfo::summary(...))->all() : [];
        $document->delete();

        return Reply::saved(['deleted' => DocumentInfo::summary($document), 'used_by' => $usedBy]);
    }

    private static function refusal(Document $document): ?string
    {
        $kind = $document->cmsKind();

        return match (true) {
            $kind === CmsKind::Page && $document->path === '/' => 'The home page cannot be deleted. Change it with save_document instead.',
            $kind?->mcpDeletable() === true => null,
            $kind?->mcpTools() !== null => "Document {$document->getKey()} is of kind {$kind->value}, which this tool does not delete. Use {$kind->mcpTools()}.",
            default => "The {$document->kind} cannot be deleted. Empty it with save_document instead.",
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The document id.'),
            'confirm' => $schema->boolean()->required()->description('Must be true.'),
        ];
    }
}
