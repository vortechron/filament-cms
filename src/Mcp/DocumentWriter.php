<?php

namespace Vortechron\FilamentCms\Mcp;

use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\SiteLanguages;

/**
 * Saves a document for the MCP server: its meta (title, path, published,
 * seo) checked here, its value checked, cleaned and kept as a version by
 * DocumentStore, as a developer. When anything is refused, nothing is
 * saved: the store writes the meta and the value in one save.
 */
final class DocumentWriter
{
    public function __construct(private readonly DocumentStore $store) {}

    /**
     * @param  array<mixed>|null  $value  a page value of any format; null keeps the stored one
     * @param  array<string, mixed>  $meta  `title`, `path`, `published`, `seo` (`title`, `description`), each optional
     * @return array{ok: bool, errors?: array<string, string>, url?: string}
     */
    public function save(Document $document, ?array $value, array $meta, ?int $expectedVersion = null): array
    {
        if (SiteLanguages::enabled() && $document->kind === Document::PAGE && is_string($meta['path'] ?? null)) {
            $meta['path'] = SiteLanguages::path($meta['path'], $document->language());
        }
        $errors = $this->metaErrors($document, $meta);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->fill($document, $meta);

        $result = $value === null && $document->exists
            ? $this->store->saveMetadata($document, $document->getDirty(), McpActor::user(), $expectedVersion)
            : $this->store->save($document, $value ?? $this->stored($document), McpActor::user(), developer: true, expectedVersion: $expectedVersion);

        return $result['ok'] && $document->kind === Document::PAGE ? [...$result, 'url' => $document->url()] : $result;
    }

    /**
     * The value an agent sent: decoded when it is a JSON string.
     *
     * @return array<mixed>|string|null the value, an error, or null when none was sent
     */
    public static function value(array $input): array|string|null
    {
        if (! array_key_exists('value', $input) || $input['value'] === null) {
            return null;
        }

        $value = Input::json($input['value']);

        if ($value === []) {
            return 'The value is empty. To remove every section, send {"version": 2, "sections": []}.';
        }

        return is_array($value) ? $value : 'The value must be a page value: {"version": 2, "sections": [...]} (see page_format), as JSON.';
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function metaErrors(Document $document, array $meta): array
    {
        $isPage = $document->kind === Document::PAGE;
        $errors = Input::errors($meta, [
            'title' => [$document->exists ? 'sometimes' : 'required', 'string', 'max:120'],
            'path' => [$isPage && ! $document->exists ? 'required' : 'sometimes', 'nullable', 'string', 'max:120'],
            'published' => ['sometimes', 'boolean'],
            'seo' => ['sometimes', 'nullable', 'array'],
            'seo.title' => ['nullable', 'string', 'max:70'],
            'seo.description' => ['nullable', 'string', 'max:170'],
        ], [
            'path.required' => 'A page needs a path, such as /tentang (/ is the home page).',
        ]);

        if (! $isPage) {
            foreach (['path' => 'Only pages have a path.', 'seo' => 'Only pages have SEO words.'] as $key => $message) {
                if (($meta[$key] ?? null) !== null) {
                    $errors[$key] = $message;
                }
            }
        }

        if ($isPage && ! isset($errors['path']) && is_string($meta['path'] ?? null) && Document::pathTaken($meta['path'], $document)) {
            $errors['path'] = 'Another page or entry already has the path '.Document::cleanPath($meta['path']).'.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function fill(Document $document, array $meta): void
    {
        $document->forceFill(array_filter([
            'title' => $meta['title'] ?? null,
            'path' => $meta['path'] ?? null,
            'is_published' => isset($meta['published']) ? filter_var($meta['published'], FILTER_VALIDATE_BOOLEAN) : null,
        ], fn (mixed $value): bool => $value !== null));

        if (is_array($meta['seo'] ?? null)) {
            foreach (['title' => 'seo_title', 'description' => 'seo_description'] as $key => $column) {
                if (array_key_exists($key, $meta['seo'])) {
                    $document->setAttribute($column, filled($meta['seo'][$key]) ? trim((string) $meta['seo'][$key]) : null);
                }
            }
        }
    }

    /**
     * @return array<mixed>
     */
    private function stored(Document $document): array
    {
        return DocumentInfo::value($document);
    }
}
