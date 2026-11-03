<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentCms\Mcp\Tools\InsertSection;
use Vortechron\FilamentCms\Mcp\Tools\MoveSection;
use Vortechron\FilamentCms\Mcp\Tools\RemoveSection;
use Vortechron\FilamentCms\Mcp\Tools\UpdateSection;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;

class SectionToolsTest extends McpTestCase
{
    private function threeSections(): SitePage
    {
        return $this->makePage('/', [$this->richSection('a', 'Satu'), $this->richSection('b', 'Dua'), $this->richSection('c', 'Tiga')]);
    }

    /**
     * @return list<string>
     */
    private function ids(SitePage $page): array
    {
        return array_column($page->fresh()->sections, 'id');
    }

    public function test_update_section_changes_one_section_and_merges_its_data(): void
    {
        $page = $this->threeSections();

        $result = $this->runTool(UpdateSection::class, ['document_id' => $page->getKey(), 'section_id' => 'b', 'section' => ['data' => ['padding' => 'lg'], 'hidden' => true]]);

        $this->assertSaved($result);
        $sections = $page->fresh()->sections;
        $this->assertSame('lg', $sections[1]['data']['padding']);
        $this->assertTrue($sections[1]['hidden']);
        $this->assertStringContainsString('Dua', json_encode($sections[1]['data']['document']));
        $this->assertSame('md', $sections[0]['data']['padding']);
        $this->assertSame(['a', 'b', 'c'], array_column($result['sections'], 'id'));
        $this->assertSame(url('/'), $result['url']);
        $this->assertSame(1, CmsRevision::query()->where('key', $page->revisionKey())->count());
    }

    public function test_update_section_refuses_a_bad_section_and_changes_nothing(): void
    {
        $page = $this->threeSections();
        $before = $page->fresh()->content;

        $this->assertRefused($this->runTool(UpdateSection::class, ['document_id' => $page->getKey(), 'section_id' => 'b', 'section' => ['type' => 'video']]), 'sections.1.type');
        $this->assertRefused($this->runTool(UpdateSection::class, ['document_id' => $page->getKey(), 'section_id' => 'zz', 'section' => ['hidden' => true]]), 'section_id');
        $this->assertRefused($this->runTool(UpdateSection::class, ['document_id' => $page->getKey(), 'section_id' => 'b']), 'section');
        $this->assertSame($before, $page->fresh()->content);
    }

    public function test_insert_section_adds_a_section_at_an_index_and_answers_its_id(): void
    {
        $page = $this->threeSections();

        $first = $this->runTool(InsertSection::class, ['document_id' => $page->getKey(), 'index' => 0, 'section' => ['type' => 'rich', 'data' => ['layout' => 'hero']]]);
        $this->assertSaved($first);
        $last = $this->runTool(InsertSection::class, ['document_id' => $page->getKey(), 'section' => $this->richSection('z', 'Akhir')]);
        $this->assertSaved($last);

        $this->assertSame([$first['section_id'], 'a', 'b', 'c', 'z'], $this->ids($page));
        $this->assertSame('hero', $page->fresh()->sections[0]['data']['layout']);
        $this->assertSame('z', $last['section_id']);
    }

    public function test_insert_section_refuses_a_taken_id_a_full_page_and_a_second_section_in_a_synced_one(): void
    {
        $page = $this->threeSections();
        $full = $this->makePage('/penuh', array_map(fn (int $n): array => $this->richSection("s{$n}", "S{$n}"), range(1, 30)));
        $synced = Document::query()->create(['kind' => Document::SECTION, 'title' => 'Promosi', 'content' => ['version' => 2, 'sections' => [$this->richSection('p', 'Diskaun')]]]);

        $this->assertRefused($this->runTool(InsertSection::class, ['document_id' => $page->getKey(), 'section' => $this->richSection('b', 'Lagi')]), 'section.id');
        $this->assertRefused($this->runTool(InsertSection::class, ['document_id' => $full->getKey(), 'section' => $this->richSection('x', 'X')]), 'sections');
        $this->assertRefused($this->runTool(InsertSection::class, ['document_id' => $synced->getKey(), 'section' => $this->richSection('q', 'Q')]), 'sections');
        $this->assertSame(['a', 'b', 'c'], $this->ids($page));
        $this->assertCount(30, $full->fresh()->sections);
    }

    public function test_move_section_moves_a_section_to_an_index(): void
    {
        $page = $this->threeSections();

        $this->assertSaved($this->runTool(MoveSection::class, ['document_id' => $page->getKey(), 'section_id' => 'c', 'index' => 0]));
        $this->assertSame(['c', 'a', 'b'], $this->ids($page));

        $this->assertSaved($this->runTool(MoveSection::class, ['document_id' => $page->getKey(), 'section_id' => 'c', 'index' => 99]));
        $this->assertSame(['a', 'b', 'c'], $this->ids($page));

        $this->assertRefused($this->runTool(MoveSection::class, ['document_id' => $page->getKey(), 'section_id' => 'c']), 'index');
    }

    public function test_remove_section_takes_one_section_out(): void
    {
        $page = $this->threeSections();

        $this->assertSaved($this->runTool(RemoveSection::class, ['document_id' => $page->getKey(), 'section_id' => 'b']));

        $this->assertSame(['a', 'c'], $this->ids($page));
        $this->assertRefused($this->runTool(RemoveSection::class, ['document_id' => $page->getKey(), 'section_id' => 'b']), 'section_id');
    }

    public function test_section_tools_only_edit_documents_that_hold_sections(): void
    {
        $kit = Document::query()->create(['kind' => Document::KIT, 'title' => 'Kit', 'content' => []]);

        $this->assertRefused($this->runTool(RemoveSection::class, ['document_id' => $kit->getKey(), 'section_id' => 'a']), 'document_id');
        $this->assertRefused($this->runTool(RemoveSection::class, ['document_id' => 999, 'section_id' => 'a']), 'document_id');
    }
}
