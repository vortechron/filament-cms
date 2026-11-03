<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentCms\Mcp\Tools\DeleteDocument;
use Vortechron\FilamentCms\Models\Document;

class DeleteDocumentTest extends McpTestCase
{
    public function test_it_deletes_a_page_when_confirmed(): void
    {
        $page = $this->makePage('/tentang');

        $result = $this->runTool(DeleteDocument::class, ['id' => $page->getKey(), 'confirm' => true]);

        $this->assertSaved($result);
        $this->assertNull(Document::query()->find($page->getKey()));
    }

    public function test_deleting_a_synced_section_names_the_documents_that_showed_it(): void
    {
        $synced = Document::query()->create(['kind' => Document::SECTION, 'title' => 'Promosi', 'content' => ['version' => 2, 'sections' => [$this->richSection('s1', 'Diskaun')]]]);
        $this->makePage('/', [['id' => 'r1', 'type' => 'ref', 'ref' => $synced->getKey()]], title: 'Utama');

        $result = $this->runTool(DeleteDocument::class, ['id' => $synced->getKey(), 'confirm' => true]);

        $this->assertSaved($result);
        $this->assertSame(['Utama'], array_column($result['used_by'], 'title'));
    }

    public function test_it_needs_confirm_true(): void
    {
        $page = $this->makePage('/tentang');

        $this->assertRefused($this->runTool(DeleteDocument::class, ['id' => $page->getKey()]), 'confirm');
        $this->assertRefused($this->runTool(DeleteDocument::class, ['id' => $page->getKey(), 'confirm' => 'yes']), 'confirm');
        $this->assertNotNull($page->fresh());
    }

    public function test_it_never_deletes_the_home_page_the_header_the_footer_the_kit_or_an_entry(): void
    {
        $keep = [
            $this->makePage('/'),
            Document::findOrCreatePart(Document::HEADER),
            Document::findOrCreatePart(Document::FOOTER),
            Document::query()->create(['kind' => Document::KIT, 'title' => 'Kit', 'content' => []]),
            Document::query()->create(['kind' => Document::ENTRY, 'entry_type' => 'product', 'title' => 'Kopi', 'content' => []]),
        ];

        foreach ($keep as $document) {
            $this->assertRefused($this->runTool(DeleteDocument::class, ['id' => $document->getKey(), 'confirm' => true]), 'id');
        }

        $this->assertSame(5, Document::query()->count());
        $this->assertRefused($this->runTool(DeleteDocument::class, ['id' => 999, 'confirm' => true]), 'id');
    }
}
