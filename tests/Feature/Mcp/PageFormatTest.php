<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentCms\Mcp\Tools\PageFormat;

class PageFormatTest extends McpTestCase
{
    /**
     * The style keys of the contract (v2-plan.md section 2).
     */
    private const STYLE_KEYS = [
        'padding_top', 'padding_right', 'padding_bottom', 'padding_left', 'margin_top', 'margin_bottom',
        'width', 'max_width', 'min_height', 'gap', 'color', 'background_color', 'border_color', 'font_family',
        'font_size', 'letter_spacing', 'font_weight', 'line_height', 'text_transform', 'text_align',
        'border_width', 'border_style', 'radius', 'shadow', 'opacity',
    ];

    private const LAYOUTS = [
        'plain', 'hero', 'cards', 'ticks', 'reviews', 'faq', 'highlight', 'banner', 'product', 'poster',
        'editorial', 'bento', 'steps', 'stats', 'compare', 'marquee',
    ];

    private const FORM_FIELD_TYPES = ['text', 'email', 'tel', 'number', 'date', 'textarea', 'select', 'radio', 'checkboxes', 'consent'];

    private function format(): string
    {
        $text = $this->runTool(PageFormat::class)['_text'];
        $this->assertIsString($text);

        return $text;
    }

    public function test_it_names_every_style_key_with_its_css_property(): void
    {
        $format = $this->format();

        foreach (self::STYLE_KEYS as $key) {
            $this->assertStringContainsString("`{$key}`", $format);
        }

        $this->assertStringContainsString('border-radius', $format);
        $this->assertStringContainsString('box-shadow', $format);
    }

    public function test_it_describes_sections_refs_locks_look_advanced_forms_and_dynamic_values(): void
    {
        $format = $this->format();

        foreach (['"version": 2', '"type": "ref"', '"lock"', '`move`', '`remove`', '`content`', '`style`', '"look"', '`advanced`', '`hide_on`', '"ownBlock"', '"form"', '"values"', '"dynamic"', '@site.whatsapp', '`kit:primary`', '`hover`', '`tablet`', '`phone`'] as $needle) {
            $this->assertStringContainsString($needle, $format);
        }

        foreach ([...self::LAYOUTS, ...self::FORM_FIELD_TYPES] as $value) {
            $this->assertStringContainsString("`{$value}`", $format);
        }
    }

    public function test_generated_parts_follow_the_site_and_nothing_is_left_unfilled(): void
    {
        config(['filament-block-editor.fonts' => ['inter' => ['label' => 'Inter', 'stack' => "'Inter', sans-serif"]]]);

        $format = $this->format();

        $this->assertStringContainsString('`inter`', $format);
        $this->assertStringContainsString('`site.phone`', $format);
        $this->assertStringContainsString('`fbe-faq`', $format);
        $this->assertStringNotContainsString('{{', $format);
    }

    public function test_it_tells_how_to_use_and_find_short_codes(): void
    {
        $format = $this->format();

        foreach (['Hubungi {site.name} di {site.phone}', 'whole `@key`', '`site_overview`', '`get_settings`'] as $needle) {
            $this->assertStringContainsString($needle, $format);
        }
    }
}
