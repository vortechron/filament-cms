<?php

namespace Vortechron\FilamentCms\Mcp\Entries;

use Illuminate\Contracts\Auth\Authenticatable;
use Vortechron\FilamentCms\Mcp\Contracts\McpEntries;

/**
 * A site without entry types.
 */
final class NoEntries implements McpEntries
{
    public const MESSAGE = 'Entries are not set up on this site: it has no entry types.';

    public function available(): bool
    {
        return false;
    }

    public function types(): array
    {
        return [];
    }

    public function list(?string $type, int $limit): array
    {
        return [];
    }

    public function save(array $entry, ?Authenticatable $user): array
    {
        return ['ok' => false, 'errors' => ['entries' => self::MESSAGE]];
    }

    public function delete(int $id): array
    {
        return ['ok' => false, 'errors' => ['entries' => self::MESSAGE]];
    }
}
