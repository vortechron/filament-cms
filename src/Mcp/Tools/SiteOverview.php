<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentBlockEditor\Design\KitSource;
use Vortechron\FilamentCms\Mcp\Contracts\McpEntries;
use Vortechron\FilamentCms\Mcp\DocumentInfo;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Mcp\SettingsInfo;
use Vortechron\FilamentCms\Mcp\Site;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\PageLimit;

#[Name('site_overview')]
#[Description('Start here. The site\'s name and address; every document except entries (pages, header, footer, synced and saved sections, card and entry page designs, the kit) with its id, kind, title, path and whether it is published; the page limit (pages.max, null for none) and pages used; the blocks you can put in a page with their fields and defaults (own blocks, core blocks included); the dynamic values with their current value; the design kit; the settings sections; the entry types; and the page format version. Read page_format next.')]
#[IsReadOnly]
class SiteOverview extends Tool
{
    public function handle(BlockRegistry $blocks, DynamicValues $values, KitSource $kit, SettingsInfo $settings, McpEntries $entries): ResponseFactory
    {
        $documents = Document::query()->where('kind', '!=', Document::ENTRY)->orderBy('kind')->orderBy('path')->orderBy('id')->get();

        return Reply::read([
            'name' => Site::name(),
            'url' => Site::url(),
            'format_version' => PageValue::VERSION,
            'documents' => $documents->map(DocumentInfo::summary(...))->all(),
            'pages' => ['max' => PageLimit::max(), 'used' => PageLimit::used()],
            'blocks' => $blocks->definitions(),
            'dynamic_values' => $values->definitions(),
            'kit' => $kit->current(),
            'settings' => $settings->sections(),
            'entries' => [
                'available' => $entries->available(),
                'types' => $entries->types(),
                'count' => Document::query()->ofKind(Document::ENTRY)->count(),
            ],
        ]);
    }
}
