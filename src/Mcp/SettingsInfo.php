<?php

namespace Vortechron\FilamentCms\Mcp;

use Filament\Forms\Components\Field as FilamentField;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\CmsSection;
use Vortechron\FilamentCms\PageContent;
use Vortechron\FilamentCms\Settings\SettingsSection;

/**
 * The site's settings sections as the MCP server shows them. A section the
 * builder's shop info form shows (it declares builderFields()) can be
 * saved through MCP, checked and cleaned the same way (SettingsWriter).
 */
final class SettingsInfo
{
    public function __construct(private readonly Cms $cms) {}

    /**
     * The sections in short, for site_overview.
     *
     * @return list<array{key: string, label: string, fields: list<string>, editable: bool}>
     */
    public function sections(): array
    {
        return array_map(fn (CmsSection $section): array => [
            'key' => $section->key,
            'label' => $section->label(),
            'fields' => array_column($this->fields($section), 'name'),
            'editable' => self::editable($section),
        ], $this->definitions());
    }

    /**
     * Each section with its field definitions and its values (saved over
     * the defaults), in the shape they are saved.
     *
     * @return list<array<string, mixed>>
     */
    public function full(): array
    {
        $saved = $this->cms->saved(Cms::SETTINGS);

        return array_map(fn (CmsSection $section): array => [
            'key' => $section->key,
            'label' => $section->label(),
            'editable' => self::editable($section),
            'fields' => $this->fields($section),
            'values' => PageContent::merge($this->cms->definition(Cms::SETTINGS), $section, is_array($saved[$section->key] ?? null) ? $saved[$section->key] : []),
        ], $this->definitions());
    }

    public static function editable(CmsSection $section): bool
    {
        return $section instanceof SettingsSection && $section->builderFields() !== [];
    }

    /**
     * @return list<CmsSection>
     */
    public function definitions(): array
    {
        return $this->cms->has(Cms::SETTINGS) ? $this->cms->definition(Cms::SETTINGS)->sections() : [];
    }

    /**
     * The builder fields of an editable section; the panel fields' names of another.
     *
     * @return list<array<string, mixed>>
     */
    private function fields(CmsSection $section): array
    {
        if ($section instanceof SettingsSection && self::editable($section)) {
            return array_map(fn (Field $field): array => $field->toArray(), $section->builderFields());
        }

        return array_values(array_map(
            fn (FilamentField $field): array => ['name' => $field->getName()],
            array_filter($section->fields(), fn (mixed $field): bool => $field instanceof FilamentField),
        ));
    }
}
