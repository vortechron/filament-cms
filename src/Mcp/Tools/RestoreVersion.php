<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Mcp\Documents;
use Vortechron\FilamentCms\Mcp\Input;
use Vortechron\FilamentCms\Mcp\McpActor;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;

#[Name('restore_version')]
#[Description('Makes an older version of a document (from list_versions) its current value again, through the store, like the restore button in the panel. For an entry it brings back the sections and keeps the stored fields (change those with save_entry). The restore is kept as a new version, so it can be undone too.')]
class RestoreVersion extends Tool
{
    public function handle(Request $request, DocumentStore $store): ResponseFactory
    {
        if ($errors = Input::errors($request->all(), ['expected_version' => ['sometimes', 'integer', 'min:0']])) {
            return Reply::refused($errors);
        }
        $document = Documents::byId($request->get('document_id'), 'document_id');

        if ($document instanceof ResponseFactory) {
            return $document;
        }

        $id = $request->get('version_id');
        $version = is_int($id) || (is_string($id) && ctype_digit($id))
            ? CmsRevision::query()->where('key', $document->revisionKey())->find((int) $id)
            : null;

        if ($version === null) {
            return Reply::refuse('version_id', "Document {$document->getKey()} has no version ".json_encode($id).'. list_versions shows its versions.');
        }

        $result = $store->restore($document, $version, McpActor::user(), developer: true, expectedVersion: $request->get('expected_version') === null ? null : (int) $request->get('expected_version'));

        return Reply::fromResult($result, $document->kind === Document::PAGE ? ['url' => $document->url()] : []);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'expected_version' => $schema->integer()->description('Required for shared documents: edit_version from get_document.'),
            'document_id' => $schema->integer()->required()->description('The document id.'),
            'version_id' => $schema->integer()->required()->description('The version id, from list_versions.'),
        ];
    }
}
