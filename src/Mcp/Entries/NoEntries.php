<?php

namespace Vortechron\FilamentCms\Mcp\Entries;

use Vortechron\FilamentCms\Mcp\Contracts\McpEntries;

/**
 * A site without entry types.
 */
final class NoEntries implements McpEntries
{
    public function available(): bool
    {
        return false;
    }

    public function types(): array
    {
        return [];
    }
}
