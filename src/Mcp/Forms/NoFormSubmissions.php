<?php

namespace Vortechron\FilamentCms\Mcp\Forms;

use Vortechron\FilamentCms\Mcp\Contracts\McpFormSubmissions;

/**
 * A site that does not save form submissions yet.
 */
final class NoFormSubmissions implements McpFormSubmissions
{
    public const MESSAGE = 'Forms are not set up on this site: it does not save form submissions.';

    public function available(): bool
    {
        return false;
    }

    public function list(array $filters): array
    {
        return ['submissions' => [], 'counts' => array_fill_keys(self::STATUSES, 0)];
    }

    public function update(int $id, string $status): array
    {
        return ['ok' => false, 'errors' => ['forms' => self::MESSAGE]];
    }
}
