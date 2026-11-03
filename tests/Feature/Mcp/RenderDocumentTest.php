<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Mcp\Tools\RenderDocument;
use Vortechron\FilamentCms\Models\Document;

class RenderDocumentTest extends McpTestCase
{
    private function html(array $arguments): string
    {
        $html = $this->runTool(RenderDocument::class, $arguments)['_text'] ?? null;
        $this->assertFalse($this->lastToolFailed);
        $this->assertIsString($html);

        return $html;
    }

    public function test_it_draws_a_published_page_as_visitors_see_it(): void
    {
        $page = $this->makePage('/tentang', [$this->richSection('sec00001', 'Kami di Batu Pahat')], title: 'Tentang');

        $byId = $this->html(['id' => $page->getKey()]);

        $this->assertStringContainsString('data-test-page="/tentang"', $byId);
        $this->assertStringContainsString('Kami di Batu Pahat', $byId);
        $this->assertStringNotContainsString('data-item=', $byId);
        $this->assertSame($byId, $this->html(['path' => '/tentang']));
    }

    public function test_an_unpublished_page_is_drawn_only_when_asked_for(): void
    {
        $page = $this->makePage('/draf', [$this->richSection('sec00001', 'Belum siap')], published: false);

        $this->assertRefused($this->runTool(RenderDocument::class, ['id' => $page->getKey()]), 'include_unpublished');
        $this->assertStringContainsString('Belum siap', $this->html(['id' => $page->getKey(), 'include_unpublished' => true]));
    }

    public function test_the_header_is_public_and_a_synced_section_needs_include_unpublished(): void
    {
        $header = Document::findOrCreatePart(Document::HEADER);
        app(DocumentStore::class)->save($header, [$this->richSection('h1', 'Logo kedai')], null, developer: true);
        $synced = Document::query()->create(['kind' => Document::SECTION, 'title' => 'Promosi', 'content' => ['version' => 2, 'sections' => [$this->richSection('p', 'Diskaun raya')]]]);

        $this->assertStringContainsString('Logo kedai', $this->html(['id' => $header->getKey()]));
        $this->assertRefused($this->runTool(RenderDocument::class, ['id' => $synced->getKey()]), 'include_unpublished');
        $this->assertStringContainsString('Diskaun raya', $this->html(['id' => $synced->getKey(), 'include_unpublished' => true]));
    }

    public function test_a_long_page_is_cut_with_a_note(): void
    {
        $long = str_repeat('Sambal ikan bilis yang pedas. ', 2000);
        $page = $this->makePage('/', array_map(fn (int $n): array => $this->richSection("s{$n}", $long), range(1, 6)));

        $html = $this->html(['id' => $page->getKey()]);

        $this->assertLessThan(201_000, strlen($html));
        $this->assertStringContainsString('cut', strtok($html, "\n"));
        $this->assertTrue(mb_check_encoding($html, 'UTF-8'));
    }

    public function test_the_kit_and_unknown_documents_are_not_drawn(): void
    {
        $kit = Document::query()->create(['kind' => Document::KIT, 'title' => 'Kit', 'content' => []]);

        $this->assertRefused($this->runTool(RenderDocument::class, ['id' => $kit->getKey()]), 'id');
        $this->assertRefused($this->runTool(RenderDocument::class, ['id' => 999]), 'id');
    }
}
