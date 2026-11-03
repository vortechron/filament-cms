<?php

namespace Vortechron\FilamentCms\Mcp;

use ReflectionClassConstant;
use Throwable;
use Vortechron\FilamentBlockEditor\BlockNote\BlockNoteDocument;
use Vortechron\FilamentBlockEditor\BlockNote\BlockNoteRenderer;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Content\PageContent;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentBlockEditor\Design\Fonts;
use Vortechron\FilamentBlockEditor\Design\Kit;
use Vortechron\FilamentBlockEditor\Enums\SectionFont;
use Vortechron\FilamentBlockEditor\Enums\SectionLayout;
use Vortechron\FilamentBlockEditor\Forms\FormDefinition;
use Vortechron\FilamentBlockEditor\Styling\Advanced;
use Vortechron\FilamentBlockEditor\Styling\CustomCss;
use Vortechron\FilamentBlockEditor\Styling\StyleBag;

/**
 * Page format 2 as text an AI can follow: resources/mcp/page-format.md
 * with its `{{name}}` parts filled from the block editor's own classes
 * (style keys, field specs, layouts, fonts, limits, the kit), so the
 * text never drifts from what the store really keeps.
 */
final class FormatGuide
{
    public function text(): string
    {
        $template = (string) file_get_contents(__DIR__.'/../../resources/mcp/page-format.md');

        return (string) preg_replace_callback('/\{\{(\w+)\}\}/', fn (array $match): string => $this->parts()[$match[1]] ?? $match[0], $template);
    }

    /**
     * @return array<string, string>
     */
    private function parts(): array
    {
        return [
            'max_sections' => (string) PageContent::MAX_SECTIONS,
            'max_name' => (string) PageContent::MAX_NAME_LENGTH,
            'max_document_kb' => (string) intdiv(BlockNoteDocument::MAX_BYTES, 1000),
            'section_fields' => $this->sectionFields(),
            'layouts' => $this->layouts(),
            'locks' => self::codes(PageContent::LOCKS),
            'style_variants' => self::codes(StyleBag::VARIANTS),
            'hover_keys' => self::codes(StyleBag::HOVER_KEYS),
            'style_keys' => $this->styleKeys(),
            'fonts' => self::codes(array_keys(Fonts::all())),
            'shadows' => self::codes(StyleBag::SHADOWS),
            'max_classes' => (string) Advanced::MAX_CLASSES,
            'devices' => self::codes(Advanced::DEVICES),
            'max_css' => (string) CustomCss::MAX_LENGTH,
            'named_colors' => self::codes(array_keys(self::privateConstant(BlockNoteRenderer::class, 'TEXT_COLORS', []))),
            'item_spacing' => self::codes(array_keys(self::privateConstant(BlockNoteRenderer::class, 'ITEM_SPACING', ['sm' => 1, 'md' => 1, 'lg' => 1, 'xl' => 1]))),
            'width_columns' => (string) BlockNoteRenderer::WIDTH_COLUMNS,
            'max_width_columns' => (string) (BlockNoteRenderer::WIDTH_COLUMNS + BlockNoteRenderer::BLEED_STEPS),
            'max_gallery' => (string) Blocks::MAX_GALLERY_PHOTOS,
            'button_variants' => self::codes(Blocks::BUTTON_VARIANTS),
            'field_types' => self::codes(Field::TYPES),
            'own_blocks' => self::codes(array_keys(app(BlockRegistry::class)->all())) ?: 'none',
            'dynamic_key' => '^'.DynamicValues::KEY.'$',
            'dynamic_values' => $this->dynamicValues(),
            'form_field_types' => self::codes(FormDefinition::FIELD_TYPES),
            'max_fields' => (string) FormDefinition::MAX_FIELDS,
            'max_options' => (string) FormDefinition::MAX_OPTIONS,
            'max_label' => (string) FormDefinition::MAX_LABEL,
            'choice_types' => self::codes(FormDefinition::CHOICE_TYPES),
            'form_aligns' => self::codes(FormDefinition::ALIGNS),
            'form_actions' => self::codes(FormDefinition::ACTIONS),
            'kit_defaults' => (string) json_encode(Kit::defaults(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'kit_colors' => self::codes(Kit::COLORS),
            'max_custom_colors' => (string) Kit::MAX_CUSTOM_COLORS,
            'button_styles' => self::codes(Kit::BUTTON_STYLES),
            'button_sizes' => self::codes(Kit::BUTTON_SIZES),
            'text_transforms' => self::codes(Kit::TEXT_TRANSFORMS),
            'form_styles' => self::codes(Kit::FORM_STYLES),
            'form_labels' => self::codes(Kit::FORM_LABELS),
            'format_version' => (string) PageValue::VERSION,
        ];
    }

    /**
     * Each field of a rich section's data, its values and its default.
     */
    private function sectionFields(): string
    {
        $defaults = PageContent::normalize([['type' => PageContent::TYPE, 'data' => []]])[0]['data'];
        $specs = self::privateConstant(PageContent::class, 'FIELDS', array_fill_keys(array_keys($defaults), ''));
        $rows = [];

        foreach ($defaults as $name => $default) {
            $rows[] = '| `'.$name.'` | '.self::fieldValues($name, (string) ($specs[$name] ?? '')).' | `'.json_encode($default).'` |';
        }

        return "| Field | Values | Default |\n| --- | --- | --- |\n".implode("\n", $rows);
    }

    private static function fieldValues(string $name, string $spec): string
    {
        if (str_starts_with($spec, 'enum:')) {
            return self::codes(explode(',', substr($spec, 5)));
        }

        return match ($spec) {
            'blocknote' => 'a list of blocks (see "Blocks")',
            'layout' => 'a layout (see "Layouts")',
            'font' => 'the heading font: '.self::codes(array_map(fn (SectionFont $font): string => $font->value, SectionFont::cases())),
            'color' => $name === 'text_color' ? '`#rrggbb`, or `null` to pick dark or white text by itself' : '`#rrggbb` or `null`',
            'image' => 'the background picture: `https://…` or a `/path`, or `null`',
            'percent' => 'darkness over the background picture, 0 to 100',
            'boolean' => '`true` or `false` (words on a card over the picture)',
            'anchor' => 'a slug `[a-z0-9]+(-[a-z0-9]+)*`, so `#slug` links reach the section, or `null`',
            default => 'see the field',
        };
    }

    private function layouts(): string
    {
        return implode("\n", array_map(
            fn (SectionLayout $layout): string => '- `'.$layout->value.'` ('.$layout->label().'): '.$layout->description(),
            SectionLayout::cases(),
        ));
    }

    private function styleKeys(): string
    {
        $types = [
            'length+' => 'length ≥ 0', 'length' => 'length', 'color' => 'color', 'font' => 'font', 'weight' => 'weight',
            'line_height' => 'line_height', 'radius' => 'radius', 'shadow' => 'shadow', 'opacity' => 'opacity',
        ];
        $rows = [];

        foreach (self::privateConstant(StyleBag::class, 'KEYS', []) as $key => [$property, $type]) {
            $value = str_starts_with($type, 'enum:') ? self::codes(explode(',', substr($type, 5))) : ($types[$type] ?? $type);
            $rows[] = "| `{$key}` | {$property} | {$value} |";
        }

        return "| Key | CSS | Value |\n| --- | --- | --- |\n".implode("\n", $rows);
    }

    private function dynamicValues(): string
    {
        $byType = [];

        foreach (app(DynamicValues::class)->definitions() as $definition) {
            $byType[$definition['type']][] = $definition['key'];
        }

        $lines = [];

        foreach (DynamicValues::TYPES as $type) {
            $lines[] = '- '.ucfirst($type).': '.(self::codes($byType[$type] ?? []) ?: 'none');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $values
     */
    private static function codes(array $values): string
    {
        return implode(' ', array_map(fn (string $value): string => "`{$value}`", $values));
    }

    /**
     * A private constant of the block editor (read only), or the fallback
     * when a later release renames it.
     *
     * @param  class-string  $class
     */
    private static function privateConstant(string $class, string $name, array $fallback): array
    {
        try {
            $value = (new ReflectionClassConstant($class, $name))->getValue();
        } catch (Throwable) {
            return $fallback;
        }

        return is_array($value) ? $value : $fallback;
    }
}
