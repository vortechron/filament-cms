<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Mcp\Tools\CreateDocument;
use Vortechron\FilamentCms\Mcp\Tools\SaveDocument;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Workbench\App\Models\User;

class WriteDocumentsTest extends McpTestCase
{
    public function test_save_document_saves_the_value_and_meta_through_the_store_as_the_mcp_user(): void
    {
        $agent = User::factory()->create(['email' => 'ai@vortechron.com']);
        config(['filament-cms.mcp.user' => 'ai@vortechron.com']);
        $page = $this->makePage('/lama', [$this->richSection('sec00001', 'Asal')], published: false);

        $result = $this->runTool(SaveDocument::class, [
            'id' => $page->getKey(),
            'value' => ['version' => 2, 'sections' => [$this->richSection('sec00001', 'Baru'), ['id' => 'sec00002', 'type' => 'rich', 'data' => ['layout' => 'nope']]]],
            'title' => 'Tentang kami',
            'path' => 'Tentang Kami',
            'published' => true,
            'seo' => ['title' => 'Tentang', 'description' => 'Kedai di Batu Pahat'],
        ]);

        $this->assertSaved($result);
        $this->assertSame(url('/tentang-kami'), $result['url']);
        $page = $page->fresh();
        $this->assertSame(['Tentang kami', '/tentang-kami', true, 'Tentang', 'Kedai di Batu Pahat'], [$page->title, $page->path, $page->is_published, $page->seo_title, $page->seo_description]);
        $this->assertStringContainsString('Baru', json_encode($page->sections[0]));
        $this->assertSame('plain', $page->sections[1]['data']['layout']);
        $this->assertSame($agent->getKey(), CmsRevision::query()->where('key', $page->revisionKey())->latest('id')->value('user_id'));
    }

    public function test_a_refused_value_changes_nothing_and_says_why(): void
    {
        $page = $this->makePage('/', [$this->richSection('sec00001', 'Asal')]);
        app(DocumentStore::class)->save($page, $page->content, null, developer: true);
        $before = $page->fresh()->content;
        $versions = CmsRevision::query()->count();

        $result = $this->runTool(SaveDocument::class, [
            'id' => $page->getKey(),
            'value' => ['sections' => [['id' => 'sec00001', 'type' => 'video']]],
            'title' => 'Tidak disimpan',
        ]);

        $this->assertRefused($result, 'sections.0.type');
        $this->assertSame($before, $page->fresh()->content);
        $this->assertSame('Utama', $page->fresh()->title);
        $this->assertSame($versions, CmsRevision::query()->count());
    }

    public function test_an_empty_value_is_refused_so_a_page_is_never_wiped_by_mistake(): void
    {
        $page = $this->makePage('/', [$this->richSection('sec00001', 'Asal')]);

        $this->assertRefused($this->runTool(SaveDocument::class, ['id' => $page->getKey(), 'value' => []]), 'value');
        $this->assertCount(1, $page->fresh()->sections);

        $this->assertSaved($this->runTool(SaveDocument::class, ['id' => $page->getKey(), 'value' => ['version' => 2, 'sections' => []]]));
        $this->assertSame([], $page->fresh()->sections);
    }

    public function test_the_mcp_saves_as_a_developer_so_it_may_change_locks_and_locked_words(): void
    {
        $page = $this->makePage('/');
        app(DocumentStore::class)->save($page, [$this->richSection('sec00001', 'Asal', ['content' => true])], null, developer: true);

        $result = $this->runTool(SaveDocument::class, ['id' => $page->getKey(), 'value' => json_encode([$this->richSection('sec00001', 'Diubah')])]);

        $this->assertSaved($result);
        $this->assertArrayNotHasKey('lock', $page->fresh()->sections[0]);
        $this->assertStringContainsString('Diubah', json_encode($page->fresh()->sections[0]));
    }

    public function test_save_document_refuses_a_taken_path_a_bad_value_and_kinds_with_their_own_tools(): void
    {
        $this->makePage('/');
        $page = $this->makePage('/tentang');
        $kit = Document::query()->create(['kind' => Document::KIT, 'title' => 'Kit', 'content' => []]);
        $header = Document::findOrCreatePart(Document::HEADER);

        $this->assertRefused($this->runTool(SaveDocument::class, ['id' => $page->getKey(), 'path' => '/']), 'path');
        $this->assertRefused($this->runTool(SaveDocument::class, ['id' => $page->getKey(), 'value' => '{not json']), 'value');
        $this->assertRefused($this->runTool(SaveDocument::class, ['id' => $kit->getKey(), 'value' => []]), 'id');
        $this->assertRefused($this->runTool(SaveDocument::class, ['id' => $header->getKey(), 'path' => '/header']), 'path');
        $this->assertRefused($this->runTool(SaveDocument::class, ['id' => 999, 'title' => 'X']), 'id');
        $this->assertSame('/tentang', $page->fresh()->path);
    }

    public function test_create_document_makes_a_page_unpublished_by_default(): void
    {
        $result = $this->runTool(CreateDocument::class, [
            'kind' => 'page',
            'title' => 'Hubungi',
            'path' => '/hubungi',
            'value' => ['version' => 2, 'sections' => [$this->richSection('sec00001', 'Telefon kami')]],
        ]);

        $this->assertSaved($result);
        $page = SitePage::query()->findOrFail($result['id']);
        $this->assertSame(['Hubungi', '/hubungi', false], [$page->title, $page->path, $page->is_published]);
        $this->assertSame(url('/hubungi'), $result['url']);
        $this->assertSame(1, CmsRevision::query()->where('key', $page->revisionKey())->count());
    }

    public function test_create_document_makes_a_synced_section_and_refuses_what_the_store_refuses(): void
    {
        $made = $this->runTool(CreateDocument::class, ['kind' => 'section', 'title' => 'Promosi', 'value' => [$this->richSection('sec00001', 'Diskaun')]]);
        $this->assertSaved($made);
        $this->assertSame('section', Document::query()->findOrFail($made['id'])->kind);
        $this->assertNull(Document::query()->findOrFail($made['id'])->path);

        $refused = $this->runTool(CreateDocument::class, ['kind' => 'template', 'title' => 'Dua', 'value' => [$this->richSection('a', 'Satu'), $this->richSection('b', 'Dua')]]);
        $this->assertRefused($refused, 'sections');
        $this->assertSame(0, Document::query()->ofKind(Document::TEMPLATE)->count());
    }

    public function test_create_document_refuses_a_second_header_a_missing_path_and_other_kinds(): void
    {
        $this->makePage('/');
        Document::findOrCreatePart(Document::HEADER);

        $this->assertRefused($this->runTool(CreateDocument::class, ['kind' => 'header', 'title' => 'Header 2']), 'kind');
        $this->assertRefused($this->runTool(CreateDocument::class, ['kind' => 'page', 'title' => 'Tiada alamat']), 'path');
        $this->assertRefused($this->runTool(CreateDocument::class, ['kind' => 'page', 'title' => 'Dua', 'path' => '/']), 'path');
        $this->assertRefused($this->runTool(CreateDocument::class, ['kind' => 'kit', 'title' => 'Kit']), 'kind');
        $this->assertRefused($this->runTool(CreateDocument::class, ['kind' => 'page', 'path' => '/x']), 'title');
        $this->assertSame(2, Document::query()->count());
    }

    public function test_create_document_refuses_a_page_past_the_site_page_limit_but_still_makes_sections(): void
    {
        config(['filament-cms.max_pages' => 2]);
        $this->makePage('/');
        $this->makePage('/tentang', title: 'Tentang');

        $refused = $this->runTool(CreateDocument::class, ['kind' => 'page', 'title' => 'Ketiga', 'path' => '/ketiga']);

        $this->assertRefused($refused, 'kind');
        $this->assertStringContainsString('at most 2 pages', $refused['errors']['kind']);
        $this->assertStringContainsString('CMS_MAX_PAGES', $refused['errors']['kind']);
        $this->assertSame(2, SitePage::query()->count());
        $this->assertSaved($this->runTool(CreateDocument::class, ['kind' => 'section', 'title' => 'Promosi', 'value' => [$this->richSection('sec00001', 'Diskaun')]]));
    }
}
