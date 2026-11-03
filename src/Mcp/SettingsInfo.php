<?php

namespace Vortechron\FilamentCms\Mcp;

use Filament\Forms\Components\Field as FilamentField;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\CmsSection;
use Vortechron\FilamentCms\Settings\SettingsSection;

/**
 * The site's settings sections as the MCP server shows them. A section the
 * builder's shop info form shows (it declares builderFields()) can be
 * saved through MCP, checked and cleaned the same way.
 */
final class SettingsInfo
{
    public function __construct(private readonly Cms $cms) {}

    /**
     * @return list<array{key: string, label: string, fields: list<string>, editable: bool}>
     */
    public function sections(): array
    {
        if (! $this->cms->has(Cms::SETTINGS)) {
            return [];
        }

        return array_map(fn (CmsSection $section): array => [
            'key' => $section->key,
            'label' => $section->label(),
            'fields' => $this->fieldNames($section),
            'editable' => self::editable($section),
        ], $this->cms->definition(Cms::SETTINGS)->sections());
    }

    public static function editable(CmsSection $section): bool
    {
        return $section instanceof SettingsSection && $section->builderFields() !== [];
    }

    /**
     * @return list<string>
     */
    private function fieldNames(CmsSection $section): array
    {
        if (self::editable($section)) {
            /** @var SettingsSection $section */
            return array_map(fn (Field $field): string => $field->name, $section->builderFields());
        }

        return array_values(array_map(
            fn (FilamentField $field): string => $field->getName(),
            array_filter($section->fields(), fn (mixed $field): bool => $field instanceof FilamentField),
        ));
    }
}
