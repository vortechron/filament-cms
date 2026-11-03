<?php

namespace Vortechron\FilamentCms\Mcp;

use Closure;
use Laravel\Mcp\ResponseFactory;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\Models\Document;

/**
 * Finds the document a tool is about, by id or by path.
 */
final class Documents
{
    /**
     * @param  array<string, mixed>  $input  `id`, or `path` for a page or entry
     * @return Document|array<string, string> the document, or errors
     */
    public static function find(array $input): Document|array
    {
        $errors = Input::errors($input, [
            'id' => ['required_without:path', 'nullable', 'integer', 'min:1'],
            'path' => ['nullable', 'string', 'max:200'],
        ], ['id.required_without' => 'Give the document id, or the path of a page.']);

        if ($errors !== []) {
            return $errors;
        }

        if (isset($input['id'])) {
            return Document::query()->find((int) $input['id']) ?? ['id' => "There is no document with id {$input['id']}."];
        }

        $path = Document::cleanPath((string) $input['path']);

        return Document::query()->where('path', $path)->first() ?? ['path' => "No page or entry has the path {$path}."];
    }

    /**
     * The document with this id when it is of one of the kinds, or the
     * refusal under `$key`.
     *
     * @param  (Closure(CmsKind): bool)|null  $kinds  which kinds the tool takes; null: all
     */
    public static function byId(mixed $id, string $key, ?Closure $kinds = null): Document|ResponseFactory
    {
        $document = is_int($id) || (is_string($id) && ctype_digit($id)) ? Document::query()->find((int) $id) : null;

        if ($document === null) {
            return Reply::refuse($key, 'There is no document with id '.json_encode($id).'.');
        }

        $kind = $document->cmsKind();

        if ($kind !== null && ($kinds === null || $kinds($kind))) {
            return $document;
        }

        $hint = $kind?->mcpTools() === null ? '' : " Use {$kind->mcpTools()}.";

        return Reply::refuse($key, "Document {$document->getKey()} is of kind {$document->kind}, which this tool does not change.{$hint}");
    }
}
