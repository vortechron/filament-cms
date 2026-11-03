<?php

namespace Vortechron\FilamentCms\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Vortechron\FilamentCms\Mcp\Tools\CreateDocument;
use Vortechron\FilamentCms\Mcp\Tools\GetDocument;
use Vortechron\FilamentCms\Mcp\Tools\ListDocuments;
use Vortechron\FilamentCms\Mcp\Tools\PageFormat;
use Vortechron\FilamentCms\Mcp\Tools\SaveDocument;
use Vortechron\FilamentCms\Mcp\Tools\SiteOverview;

/**
 * The site's MCP server: an AI agent reads and edits the site's content
 * here with no deploy. It acts as a developer, and every write goes
 * through the same store as the panel (DocumentStore, ContentStore), so it
 * is checked, cleaned and kept as a version. Registered by
 * FilamentCms::mcp().
 */
#[Name('Filament CMS')]
#[Version('1.0.0')]
class CmsServer extends Server
{
    /**
     * Every tool in one page of tools/list.
     */
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        SiteOverview::class,
        PageFormat::class,
        ListDocuments::class,
        GetDocument::class,
        CreateDocument::class,
        SaveDocument::class,
    ];

    protected function boot(): void
    {
        $this->instructions = Site::instructions();
    }
}
