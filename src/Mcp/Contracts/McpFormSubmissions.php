<?php

namespace Vortechron\FilamentCms\Mcp\Contracts;

/**
 * What visitors sent through the site's forms, as the MCP server reads and
 * marks them. Bound to EloquentFormSubmissions when the forms track's
 * Models\FormSubmission exists, else to NoFormSubmissions.
 */
interface McpFormSubmissions
{
    public const STATUSES = ['new', 'read', 'spam'];

    public function available(): bool;

    /**
     * Newest first. Filters, each optional: `status`, `form_key`,
     * `document_id`, `before_id` (older than this id), `limit`.
     *
     * @param  array<string, mixed>  $filters
     * @return array{submissions: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function list(array $filters): array;

    /**
     * @param  value-of<self::STATUSES>  $status
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function update(int $id, string $status): array;
}
