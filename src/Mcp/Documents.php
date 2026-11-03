<?php

namespace Vortechron\FilamentCms\Mcp;

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
}
