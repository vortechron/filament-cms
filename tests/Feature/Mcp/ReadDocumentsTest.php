<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Mcp\Tools\GetDocument;
use Vortechron\FilamentCms\Mcp\Tools\ListDocuments;
use Vortechron\FilamentCms\Models\Document;

class ReadDocumentsTest extends McpTestCase
{
    public function test_it_lists_documents_and_filters_them_by_kind(): void
    {
        $home = $this->makePage('/');
        $this->makePage('/tentang', title: 'Tentang');
        $header = Document::findOrCreatePart(Document::HEADER);

        $all = $this->runTool(ListDocuments::class);
        $parts = $this->runTool(ListDocuments::class, ['kind' => 'header']);

        $this->assertCount(3, $all['documents']);
        $this->assertSame([$header->getKey()], array_column($parts['documents'], 'id'));
        $this->assertContains($home->getKey(), array_column($all['documents'], 'id'));
    }

    public function test_an_unknown_kind_is_refused_in_plain_words(): void
    {
        $result = $this->runTool(ListDocuments::class, ['kind' => 'blog']);

        $this->assertRefused($result, 'kind');
    }

    public function test_it_gets_a_document_by_id_or_by_path_with_its_value_and_meta(): void
    {
        $page = $this->makePage('/tentang', [$this->richSection('sec00001', 'Kami di Batu Pahat')], title: 'Tentang');
        $page->forceFill(['seo_title' => 'Tentang kami', 'seo_description' => 'Kedai sejak 1990'])->save();

        $byId = $this->runTool(GetDocument::class, ['id' => $page->getKey()]);
        $byPath = $this->runTool(GetDocument::class, ['path' => 'tentang']);

        $this->assertSame($byId, $byPath);
        $this->assertSame('page', $byId['kind']);
        $this->assertSame('/tentang', $byId['path']);
        $this->assertSame(url('/tentang'), $byId['url']);
        $this->assertSame(['title' => 'Tentang kami', 'description' => 'Kedai sejak 1990'], $byId['seo']);
        $this->assertSame(2, $byId['value']['version']);
        $this->assertSame('sec00001', $byId['value']['sections'][0]['id']);
        $this->assertSame('plain', $byId['value']['sections'][0]['data']['layout']);
    }

    public function test_the_kit_document_shows_the_cleaned_kit(): void
    {
        $kit = new Document(['kind' => Document::KIT, 'title' => 'Kit']);
        app(DocumentStore::class)->save($kit, ['radius' => '8px'], null, developer: true);

        $result = $this->runTool(GetDocument::class, ['id' => $kit->getKey()]);

        $this->assertSame('8px', $result['value']['radius']);
        $this->assertArrayHasKey('primary', $result['value']['colors']);
    }

    public function test_a_document_that_does_not_exist_is_refused(): void
    {
        $this->assertRefused($this->runTool(GetDocument::class, ['id' => 999]), 'id');
        $this->assertRefused($this->runTool(GetDocument::class, ['path' => '/tiada']), 'path');
        $this->assertRefused($this->runTool(GetDocument::class, []), 'id');
    }
}
