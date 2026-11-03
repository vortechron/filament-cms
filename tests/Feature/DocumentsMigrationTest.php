<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SitePageStore;
use Vortechron\FilamentCms\Tests\TestCase;

class DocumentsMigrationTest extends TestCase
{
    private function migration(): Migration
    {
        return require __DIR__.'/../../database/migrations/2026_11_03_100000_move_cms_pages_to_cms_documents.php';
    }

    /**
     * A section as the 0.x CMS stored it in cms_pages.sections (format 1).
     *
     * @return array<string, mixed>
     */
    private function oldSection(string $id, string $text): array
    {
        return ['id' => $id, 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph($text)], 'layout' => 'plain', 'padding' => 'md']];
    }

    /**
     * @param  array<mixed>  $sections
     */
    private function insertOldPage(int $id, string $path, array $sections): void
    {
        DB::table('cms_pages')->insert([
            'id' => $id,
            'title' => "Halaman {$id}",
            'path' => $path,
            'sections' => json_encode($sections),
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_format_1_pages_move_into_documents_as_format_2_and_keep_their_ids(): void
    {
        $migration = $this->migration();
        $migration->down();

        $this->insertOldPage(7, '/', [$this->oldSection('sec00001', 'Selamat datang')]);
        $this->insertOldPage(9, '/tentang', ['sections' => [$this->oldSection('sec00002', 'Tentang kami'), ['id' => 'junk', 'type' => 'nope']]]);

        $migration->up();

        $this->assertFalse(Schema::hasTable('cms_pages'));
        $this->assertTrue(Schema::hasColumns('cms_documents', ['kind', 'entry_type', 'sort', 'settings', 'content']));
        $this->assertFalse(Schema::hasColumn('cms_documents', 'sections'));

        $home = SitePage::query()->findOrFail(7);
        $this->assertSame(Document::PAGE, $home->kind);
        $this->assertSame(2, $home->content['version']);
        $this->assertSame('sec00001', $home->sections[0]['id']);
        $this->assertSame('page:7', $home->revisionKey());

        $about = SitePage::query()->findOrFail(9);
        $this->assertSame(['sec00002'], array_column($about->content['sections'], 'id'));

        $this->get('/')->assertOk()->assertSee('Selamat datang');
        $this->get('/tentang')->assertOk()->assertSee('Tentang kami');
    }

    public function test_an_old_version_of_a_moved_page_can_still_be_restored(): void
    {
        $migration = $this->migration();
        $migration->down();
        $this->insertOldPage(7, '/', [$this->oldSection('sec00001', 'Baru')]);
        CmsRevision::query()->create(['key' => 'page:7', 'content' => [$this->oldSection('sec00001', 'Lama')]]);

        $migration->up();

        $page = SitePage::query()->findOrFail(7);
        app(SitePageStore::class)->restore($page, CmsRevision::query()->where('key', 'page:7')->firstOrFail(), null);

        $this->get('/')->assertSee('Lama')->assertDontSee('Baru');
    }

    public function test_a_document_may_have_no_path_but_paths_stay_unique(): void
    {
        Document::query()->create(['kind' => Document::HEADER, 'title' => 'Header', 'content' => ['version' => 2, 'sections' => []]]);
        Document::query()->create(['kind' => Document::FOOTER, 'title' => 'Footer', 'content' => ['version' => 2, 'sections' => []]]);
        SitePage::query()->create(['title' => 'Satu', 'path' => '/sama', 'sections' => []]);

        $this->assertSame(2, Document::query()->whereNull('path')->count());

        $this->expectException(QueryException::class);
        SitePage::query()->create(['title' => 'Dua', 'path' => '/sama', 'sections' => []]);
    }

    public function test_rolling_back_puts_the_pages_back_in_format_1_and_drops_other_documents(): void
    {
        SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'sections' => [$this->oldSection('sec00001', 'Kekal')]]);
        Document::query()->create(['kind' => Document::HEADER, 'title' => 'Header', 'content' => ['version' => 2, 'sections' => []]]);

        $migration = $this->migration();
        $migration->down();

        $this->assertFalse(Schema::hasTable('cms_documents'));
        $rows = DB::table('cms_pages')->get();
        $this->assertCount(1, $rows);
        $sections = json_decode($rows[0]->sections, true);
        $this->assertTrue(array_is_list($sections));
        $this->assertSame('sec00001', $sections[0]['id']);

        $migration->up();
        $this->get('/')->assertSee('Kekal');
    }
}
