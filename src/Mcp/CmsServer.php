<?php

namespace Vortechron\FilamentCms\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Vortechron\FilamentCms\Mcp\Tools\CreateDocument;
use Vortechron\FilamentCms\Mcp\Tools\DeleteDocument;
use Vortechron\FilamentCms\Mcp\Tools\DeleteEntry;
use Vortechron\FilamentCms\Mcp\Tools\GetDocument;
use Vortechron\FilamentCms\Mcp\Tools\GetKit;
use Vortechron\FilamentCms\Mcp\Tools\GetSettings;
use Vortechron\FilamentCms\Mcp\Tools\InsertSection;
use Vortechron\FilamentCms\Mcp\Tools\ListDocuments;
use Vortechron\FilamentCms\Mcp\Tools\ListEntries;
use Vortechron\FilamentCms\Mcp\Tools\ListVersions;
use Vortechron\FilamentCms\Mcp\Tools\MoveSection;
use Vortechron\FilamentCms\Mcp\Tools\PageFormat;
use Vortechron\FilamentCms\Mcp\Tools\RemoveSection;
use Vortechron\FilamentCms\Mcp\Tools\RenderDocument;
use Vortechron\FilamentCms\Mcp\Tools\RestoreVersion;
use Vortechron\FilamentCms\Mcp\Tools\SaveDocument;
use Vortechron\FilamentCms\Mcp\Tools\SaveEntry;
use Vortechron\FilamentCms\Mcp\Tools\SaveKit;
use Vortechron\FilamentCms\Mcp\Tools\SaveSettings;
use Vortechron\FilamentCms\Mcp\Tools\SiteOverview;
use Vortechron\FilamentCms\Mcp\Tools\UpdateSection;

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
        DeleteDocument::class,
        UpdateSection::class,
        InsertSection::class,
        MoveSection::class,
        RemoveSection::class,
        GetKit::class,
        SaveKit::class,
        GetSettings::class,
        SaveSettings::class,
        ListEntries::class,
        SaveEntry::class,
        DeleteEntry::class,
        ListVersions::class,
        RestoreVersion::class,
        RenderDocument::class,
    ];

    protected function boot(): void
    {
        $this->instructions = Site::instructions();
    }
}
