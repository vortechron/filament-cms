<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Filament\Facades\Filament;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;
use Workbench\App\Models\User;

class DocumentStoreTest extends TestCase
{
    /**
     * @param  array<string, true>  $lock
     * @return array<string, mixed>
     */
    private function section(string $id, string $text, array $lock = []): array
    {
        return ['id' => $id, 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph($text)]], ...($lock === [] ? [] : ['lock' => $lock])];
    }

    private function page(): SitePage
    {
        return SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'sections' => []]);
    }

    private function store(): DocumentStore
    {
        return app(DocumentStore::class);
    }

    public function test_it_saves_a_cleaned_format_2_value_and_keeps_a_version(): void
    {
        $page = $this->page();

        $result = $this->store()->save($page, ['sections' => [$this->section('sec00001', 'Helo'), ['id' => 'x', 'type' => 'rich', 'data' => ['layout' => 'nope']]]], null);

        $this->assertSame(['ok' => true], $result);
        $content = $page->fresh()->content;
        $this->assertSame(2, $content['version']);
        $this->assertSame('plain', $content['sections'][1]['data']['layout']);
        $this->assertSame($content, CmsRevision::query()->where('key', 'page:'.$page->getKey())->sole()->content);
    }

    public function test_a_value_that_is_not_a_page_is_refused_with_plain_errors(): void
    {
        $page = $this->page();

        $result = $this->store()->save($page, ['sections' => [['id' => 'sec00001', 'type' => 'video']]], null);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('sections.0.type', $result['errors']);
        $this->assertIsString($result['errors']['sections.0.type']);
        $this->assertSame(0, CmsRevision::query()->count());
    }

    public function test_someone_who_is_not_a_developer_cannot_change_locks_or_locked_words(): void
    {
        $page = $this->page();
        $this->store()->save($page, [$this->section('sec00001', 'Asal', ['content' => true, 'remove' => true])], null, developer: true);

        $result = $this->store()->save($page, [$this->section('sec00001', 'Diubah'), $this->section('sec00002', 'Baru', ['move' => true])], null, developer: false);

        $this->assertTrue($result['ok']);
        $sections = $page->fresh()->sections;
        $this->assertEquals(['content' => true, 'remove' => true], $sections[0]['lock']);
        $this->assertStringContainsString('Asal', json_encode($sections[0]['data']['document']));
        $this->assertArrayNotHasKey('lock', $sections[1]);
    }

    public function test_a_developer_may_change_locks(): void
    {
        $page = $this->page();
        $this->store()->save($page, [$this->section('sec00001', 'Asal', ['content' => true])], null, developer: true);

        $this->store()->save($page, [$this->section('sec00001', 'Diubah')], null, developer: true);

        $this->assertArrayNotHasKey('lock', $page->fresh()->sections[0]);
    }

    public function test_without_a_panel_nobody_counts_as_a_developer(): void
    {
        $page = $this->page();
        $this->store()->save($page, [$this->section('sec00001', 'Asal', ['content' => true])], null, developer: true);
        $developer = User::factory()->developer()->create();

        $this->store()->save($page, [$this->section('sec00001', 'Diubah')], $developer);

        $this->assertSame(['content' => true], $page->fresh()->sections[0]['lock']);
    }

    public function test_in_the_panel_the_plugin_says_who_is_a_developer(): void
    {
        $page = $this->page();
        $this->store()->save($page, [$this->section('sec00001', 'Asal', ['content' => true])], null, developer: true);
        $developer = $this->actingAsDeveloper();
        $this->assertNotNull(Filament::getCurrentPanel());

        $this->store()->save($page, [$this->section('sec00001', 'Diubah')], $developer);

        $this->assertArrayNotHasKey('lock', $page->fresh()->sections[0]);
    }

    public function test_only_the_newest_twenty_versions_are_kept_and_identical_saves_are_skipped(): void
    {
        $page = $this->page();

        foreach (range(1, 22) as $number) {
            $this->store()->save($page, [$this->section('sec00001', "Versi {$number}")], null);
        }

        $this->store()->save($page, [$this->section('sec00001', 'Versi 22')], null);

        $versions = CmsRevision::query()->where('key', $page->revisionKey())->oldest('id')->get();
        $this->assertCount(20, $versions);
        $this->assertStringContainsString('Versi 3', json_encode($versions->first()->content));
    }

    public function test_a_new_document_is_created_by_its_first_save_and_keeps_doc_versions(): void
    {
        $header = new Document(['kind' => Document::HEADER, 'title' => 'Header']);

        $this->assertTrue($this->store()->save($header, [$this->section('sec00001', 'Logo')], null)['ok']);

        $this->assertTrue($header->exists);
        $this->assertSame('doc:'.$header->getKey(), $header->revisionKey());
        $this->assertSame(1, CmsRevision::query()->where('key', $header->revisionKey())->count());
    }

    public function test_a_synced_section_holds_one_rich_section(): void
    {
        $synced = new Document(['kind' => Document::SECTION, 'title' => 'Promosi']);

        $result = $this->store()->save($synced, [$this->section('sec00001', 'Satu'), $this->section('sec00002', 'Dua')], null);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('sections', $result['errors']);
        $this->assertFalse($synced->exists);
    }

    public function test_the_kit_is_checked_by_the_kit_rules_and_cleaned(): void
    {
        $kit = new Document(['kind' => Document::KIT, 'title' => 'Kit']);

        $refused = $this->store()->save($kit, ['colors' => 'merah'], null);
        $this->assertFalse($refused['ok']);
        $this->assertArrayHasKey('colors', $refused['errors']);

        $this->assertTrue($this->store()->save($kit, ['colors' => ['primary' => '#C59D6E', 'text' => 'red;}'], 'radius' => '8px', 'extra' => 1], null)['ok']);
        $stored = $kit->fresh()->content;
        $this->assertSame('#c59d6e', $stored['colors']['primary']);
        $this->assertSame('#475569', $stored['colors']['text']);
        $this->assertSame('8px', $stored['radius']);
        $this->assertArrayNotHasKey('extra', $stored);
    }

    public function test_restore_brings_back_a_version_of_the_same_document_only(): void
    {
        $page = $this->page();
        $other = SitePage::query()->create(['title' => 'Lain', 'path' => '/lain', 'sections' => []]);
        $this->store()->save($page, [$this->section('sec00001', 'Lama')], null);
        $old = CmsRevision::query()->where('key', $page->revisionKey())->sole();
        $this->store()->save($page, [$this->section('sec00001', 'Baru')], null);

        $this->assertTrue($this->store()->restore($page, $old, null)['ok']);
        $this->assertStringContainsString('Lama', json_encode($page->fresh()->content));

        $this->expectException(NotFoundHttpException::class);
        $this->store()->restore($other, $old, null);
    }
}
