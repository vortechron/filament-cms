<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentCms\Mcp\Tools\CreateDocument;
use Vortechron\FilamentCms\Mcp\Tools\GetDocument;
use Vortechron\FilamentCms\Mcp\Tools\SaveDocument;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\PageLimit;

class TranslationToolsTest extends McpTestCase
{
    public function test_an_agent_can_create_a_linked_draft_at_the_page_limit_and_read_its_language(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Bahasa Melayu', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-cms.max_pages' => 1]);
        $page = $this->makePage('/hubungi');
        $result = $this->runTool(CreateDocument::class, ['kind' => 'page', 'translation_of' => $page->getKey(), 'locale' => 'en']);
        $this->assertSaved($result);
        $translated = SitePage::findOrFail($result['id']);
        $this->assertFalse($translated->is_published);
        $this->assertSame(1, PageLimit::used());
        $read = $this->runTool(GetDocument::class, ['id' => $translated->getKey()]);
        $this->assertSame('en', $read['locale']);
        $this->assertSame($page->getKey(), $read['translation_root']);
    }

    public function test_saving_an_english_path_uses_the_same_language_prefix_and_uniqueness_as_the_panel(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Bahasa Melayu', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        $page = $this->makePage('/hubungi');
        $copy = $this->runTool(CreateDocument::class, ['kind' => 'page', 'translation_of' => $page->getKey(), 'locale' => 'en']);
        $this->makePage('/contact');
        $result = $this->runTool(SaveDocument::class, ['id' => $copy['id'], 'path' => '/contact']);
        $this->assertSaved($result);
        $this->assertSame('/en/contact', SitePage::findOrFail($copy['id'])->path);
    }

    public function test_agent_saves_use_english_block_defaults_on_english_pages(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Bahasa Melayu', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-block-editor.content_locale' => 'ms']);
        $page = $this->makePage('/hubungi');
        $copy = $this->runTool(CreateDocument::class, ['kind' => 'page', 'translation_of' => $page->getKey(), 'locale' => 'en']);
        $result = $this->runTool(SaveDocument::class, ['id' => $copy['id'], 'value' => ['version' => 2, 'sections' => [
            ['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [
                ['id' => 'faq00001', 'type' => 'ownBlock', 'props' => ['kind' => 'fbe-faq', 'values' => '{}'], 'content' => [], 'children' => []],
            ]]],
        ]]]);
        $this->assertSaved($result);
        $this->assertStringContainsString('Frequently asked questions', json_encode(SitePage::findOrFail($copy['id'])->content));
        $this->assertStringNotContainsString('Soalan lazim', json_encode($this->runTool(GetDocument::class, ['id' => $copy['id']])['value']));
    }
}
