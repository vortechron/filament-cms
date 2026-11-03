<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Mcp\Tools\ListVersions;
use Vortechron\FilamentCms\Mcp\Tools\RestoreVersion;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\SitePage;
use Workbench\App\Models\User;

class VersionToolsTest extends McpTestCase
{
    private function pageWithVersions(): SitePage
    {
        $page = $this->makePage('/');
        $editor = User::factory()->create(['name' => 'Aminah', 'email' => 'aminah@example.com']);

        foreach (['Satu', 'Dua', 'Tiga'] as $text) {
            app(DocumentStore::class)->save($page, [$this->richSection('sec00001', $text)], $editor, developer: true);
        }

        return $page;
    }

    public function test_list_versions_shows_the_kept_versions_newest_first(): void
    {
        $page = $this->pageWithVersions();

        $versions = $this->runTool(ListVersions::class, ['document_id' => $page->getKey()])['versions'];

        $this->assertCount(3, $versions);
        $this->assertSame(CmsRevision::query()->where('key', $page->revisionKey())->latest('id')->value('id'), $versions[0]['id']);
        $this->assertTrue($versions[0]['current']);
        $this->assertFalse($versions[2]['current']);
        $this->assertSame(['name' => 'Aminah', 'email' => 'aminah@example.com'], $versions[0]['user']);
        $this->assertStringContainsString('Satu', $versions[2]['outline'][0]['text']);
    }

    public function test_restore_version_brings_back_an_older_value_as_a_new_version(): void
    {
        $page = $this->pageWithVersions();
        $oldest = CmsRevision::query()->where('key', $page->revisionKey())->oldest('id')->firstOrFail();

        $result = $this->runTool(RestoreVersion::class, ['document_id' => $page->getKey(), 'version_id' => $oldest->getKey()]);

        $this->assertSaved($result);
        $this->assertStringContainsString('Satu', json_encode($page->fresh()->sections));
        $this->assertSame(4, CmsRevision::query()->where('key', $page->revisionKey())->count());
        $this->assertSame(url('/'), $result['url']);
    }

    public function test_a_version_of_another_document_is_refused(): void
    {
        $page = $this->pageWithVersions();
        $other = $this->makePage('/lain');
        $version = CmsRevision::query()->where('key', $page->revisionKey())->firstOrFail();

        $this->assertRefused($this->runTool(RestoreVersion::class, ['document_id' => $other->getKey(), 'version_id' => $version->getKey()]), 'version_id');
        $this->assertRefused($this->runTool(RestoreVersion::class, ['document_id' => 999, 'version_id' => $version->getKey()]), 'document_id');
        $this->assertRefused($this->runTool(ListVersions::class, ['document_id' => 999]), 'document_id');
        $this->assertSame([], $other->fresh()->sections);
    }
}
