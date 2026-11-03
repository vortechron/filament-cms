<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Mcp\Tools\GetKit;
use Vortechron\FilamentCms\Mcp\Tools\SaveKit;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;

class KitToolsTest extends McpTestCase
{
    private function storeKit(array $kit): Document
    {
        $document = new Document(['kind' => Document::KIT, 'title' => 'Kit']);
        app(DocumentStore::class)->save($document, $kit, null, developer: true);

        return $document;
    }

    public function test_get_kit_shows_the_stored_kit_over_the_defaults_and_the_fonts(): void
    {
        $document = $this->storeKit(['radius' => '8px']);

        $result = $this->runTool(GetKit::class);

        $this->assertSame('8px', $result['kit']['radius']);
        $this->assertSame('#2563eb', $result['kit']['colors']['primary']);
        $this->assertSame('1.25rem', $result['defaults']['radius']);
        $this->assertSame($document->getKey(), $result['document_id']);
        $this->assertContains('serif', array_column($result['fonts'], 'key'));
    }

    public function test_save_kit_changes_only_what_is_sent_and_keeps_a_version(): void
    {
        $document = $this->storeKit(['radius' => '8px', 'custom_colors' => [['key' => 'gold', 'label' => 'Emas', 'value' => '#c59d6e']]]);

        $result = $this->runTool(SaveKit::class, ['kit' => ['colors' => ['primary' => '#FF0000'], 'buttons' => ['style' => 'pill']]]);

        $this->assertSaved($result);
        $kit = cms_kit();
        $this->assertSame('#ff0000', $kit['colors']['primary']);
        $this->assertSame('pill', $kit['buttons']['style']);
        $this->assertSame('8px', $kit['radius']);
        $this->assertSame('gold', $kit['custom_colors'][0]['key']);
        $this->assertSame('#ff0000', $result['kit']['colors']['primary']);
        $this->assertSame(2, CmsRevision::query()->where('key', $document->revisionKey())->count());
    }

    public function test_save_kit_makes_the_kit_document_on_first_use_and_replaces_the_custom_colours_whole(): void
    {
        $this->assertSaved($this->runTool(SaveKit::class, ['kit' => ['custom_colors' => [['key' => 'gold', 'label' => 'Emas', 'value' => '#c59d6e'], ['key' => 'mint', 'value' => '#a7f3d0']]]]));
        $this->assertSaved($this->runTool(SaveKit::class, ['kit' => ['custom_colors' => [['key' => 'mint', 'value' => '#a7f3d0']]]]));

        $this->assertSame(1, Document::query()->ofKind(Document::KIT)->count());
        $this->assertSame(['mint'], array_column(cms_kit()['custom_colors'], 'key'));
    }

    public function test_save_kit_refuses_values_the_kit_would_drop_and_changes_nothing(): void
    {
        $this->storeKit(['radius' => '8px']);
        $before = cms_kit();

        $this->assertRefused($this->runTool(SaveKit::class, ['kit' => ['colors' => ['primary' => 'merah']]]), 'colors.primary');
        $this->assertRefused($this->runTool(SaveKit::class, ['kit' => ['colors' => ['pink' => '#ff00ff']]]), 'colors.pink');
        $this->assertRefused($this->runTool(SaveKit::class, ['kit' => ['buttons' => ['style' => 'glow'], 'radius' => '9000px']]), 'buttons.style', 'radius');
        $this->assertRefused($this->runTool(SaveKit::class, ['kit' => ['custom_colors' => [['key' => 'primary', 'value' => '#000000']]]]), 'custom_colors.0');
        $this->assertRefused($this->runTool(SaveKit::class, ['kit' => ['colors' => 'merah']]), 'colors');
        $this->assertRefused($this->runTool(SaveKit::class, ['kit' => 'merah']), 'kit');
        $this->assertSame($before, cms_kit());
    }
}
