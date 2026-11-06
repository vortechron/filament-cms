<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Mcp\Tools\GetDocument;
use Vortechron\FilamentCms\Mcp\Tools\ListVersions;
use Vortechron\FilamentCms\Mcp\Tools\RestoreVersion;
use Vortechron\FilamentCms\Mcp\Tools\SaveDocument;
use Vortechron\FilamentCms\Mcp\Tools\UpdateSection;

class SharedDocumentsTest extends McpTestCase
{
    public function test_existing_english_id_reads_localized_content_and_requires_the_root_version_for_writes(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        $root = $this->makePage('/contact');
        $english = app(DocumentTranslations::class)->create($root, 'en');
        $english->update(['content' => ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph('English contact')]]]]]]);
        // Both old layouts must match; the only change is visitor words.
        $root->update(['content' => ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph('Hubungi')]]]]]]);
        $this->assertTrue(app(DocumentTranslations::class)->convert($root)['ok']);
        $read = $this->runTool(GetDocument::class, ['id' => $english->id]);
        $this->assertSame(1, $read['edit_version']);
        $this->assertStringContainsString('English contact', json_encode($read['value']));
        $initialHistory = $this->runTool(ListVersions::class, ['document_id' => $english->id]);
        $layout = $this->runTool(GetDocument::class, ['id' => $root->id])['value'];
        $layout['sections'][0]['data']['padding'] = 'lg';
        $this->assertSaved($this->runTool(SaveDocument::class, ['id' => $root->id, 'value' => $layout, 'expected_version' => 1]));
        $this->assertSame($initialHistory, $this->runTool(ListVersions::class, ['document_id' => $english->id]));
        $this->assertFalse($this->runTool(SaveDocument::class, ['id' => $english->id, 'title' => 'Old client'])['ok']);
        $this->assertSaved($this->runTool(SaveDocument::class, ['id' => $english->id, 'title' => 'Contact', 'expected_version' => 2]));
        $result = $this->runTool(UpdateSection::class, ['document_id' => $english->id, 'section_id' => 'sec00001', 'section' => ['data' => ['document' => [Blocks::paragraph('Updated English')]]], 'expected_version' => 3]);
        $this->assertSaved($result);
        $this->assertStringContainsString('Updated English', json_encode($this->runTool(GetDocument::class, ['id' => $english->id])['value']));
        $versions = $this->runTool(ListVersions::class, ['document_id' => $english->id]);
        $this->assertTrue($versions['versions'][0]['current']);
        $this->assertSaved($this->runTool(RestoreVersion::class, ['document_id' => $english->id, 'version_id' => $versions['versions'][0]['id'], 'expected_version' => 4]));
        $this->assertStringContainsString('Hubungi', json_encode($this->runTool(GetDocument::class, ['id' => $root->id])['value']));
        $before = $this->runTool(ListVersions::class, ['document_id' => $english->id]);
        $current = $this->runTool(GetDocument::class, ['id' => $root->id]);
        $changed = $current['value'];
        $changed['sections'][0]['data']['padding'] = 'xl';
        $this->assertSaved($this->runTool(SaveDocument::class, ['id' => $root->id, 'value' => $changed, 'expected_version' => $current['edit_version']]));
        $this->assertSame($before, $this->runTool(ListVersions::class, ['document_id' => $english->id]));
    }
}
