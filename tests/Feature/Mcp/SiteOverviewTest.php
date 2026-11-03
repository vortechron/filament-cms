<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentBlockEditor\Blocks\OwnBlock;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Mcp\Tools\SiteOverview;
use Vortechron\FilamentCms\Models\Document;

class SiteOverviewTest extends McpTestCase
{
    public function test_it_tells_the_agent_everything_it_needs_to_start(): void
    {
        app(ContentStore::class)->save('settings', ['business' => ['name' => 'Kedai Adam'], 'contact' => ['phones' => ['012-345 6789']]], null);
        $home = $this->makePage('/', [$this->richSection('sec00001', 'Helo')]);
        $about = $this->makePage('/tentang', [], published: false, title: 'Tentang');
        $header = Document::findOrCreatePart(Document::HEADER);
        app(DocumentStore::class)->save(new Document(['kind' => Document::KIT, 'title' => 'Kit']), ['colors' => ['primary' => '#c59d6e']], null, developer: true);
        app(BlockRegistry::class)->register(OwnBlock::make('menu-board')->label('Menu')->fields([Field::text('heading')->default('Menu kami')]));

        $overview = $this->runTool(SiteOverview::class);

        $this->assertSame('Kedai Adam', $overview['name']);
        $this->assertSame(url('/'), $overview['url']);
        $this->assertSame(2, $overview['format_version']);

        $documents = array_column($overview['documents'], null, 'id');
        $this->assertSame(['id' => $home->getKey(), 'kind' => 'page', 'title' => 'Utama', 'path' => '/', 'published' => true], array_intersect_key($documents[$home->getKey()], array_flip(['id', 'kind', 'title', 'path', 'published'])));
        $this->assertFalse($documents[$about->getKey()]['published']);
        $this->assertSame('header', $documents[$header->getKey()]['kind']);
        $this->assertNull($documents[$header->getKey()]['path']);

        $blocks = array_column($overview['blocks'], null, 'kind');
        $this->assertArrayHasKey('fbe-faq', $blocks);
        $this->assertSame('heading', $blocks['menu-board']['fields'][0]['name']);
        $this->assertSame(['heading' => 'Menu kami'], $blocks['menu-board']['defaults']);

        $values = array_column($overview['dynamic_values'], null, 'key');
        $this->assertSame('012-345 6789', $values['site.phone']['preview']);
        $this->assertSame('link', $values['site.whatsapp']['type']);

        $this->assertSame('#c59d6e', $overview['kit']['colors']['primary']);

        $this->assertSame(['business', 'contact', 'hours', 'social', 'seo'], array_column($overview['settings'], 'key'));
        $contact = array_column($overview['settings'], null, 'key')['contact'];
        $this->assertContains('phones', $contact['fields']);

        $this->assertFalse($overview['entries']['available']);
        $this->assertSame([], $overview['entries']['types']);
    }
}
