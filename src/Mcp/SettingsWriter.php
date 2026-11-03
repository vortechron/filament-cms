<?php

namespace Vortechron\FilamentCms\Mcp;

use Illuminate\Validation\ValidationException;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\PageContent;
use Vortechron\FilamentCms\Settings\SettingsSection;
use Vortechron\FilamentCms\SiteInfo;

/**
 * Saves settings for the MCP server. The agent sends values in the shape
 * get_settings shows them (phones as a list of numbers, a picture as its
 * path or address), section by section; each is merged over the stored
 * values and saved by SiteInfo::save(), so it is checked by the section's
 * builderRules(), cleaned by its fields and saved through ContentStore with
 * a version, exactly like the builder's shop info form. Only sections that
 * form shows can be saved here.
 */
final class SettingsWriter
{
    public function __construct(private readonly Cms $cms, private readonly SettingsInfo $info, private readonly SiteInfo $siteInfo) {}

    /**
     * @param  array<mixed>  $changes  by section key, then field name
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function save(array $changes): array
    {
        $sections = [];

        foreach ($this->info->definitions() as $section) {
            $sections[$section->key] = $section;
        }

        $errors = $this->keyErrors($changes, $sections);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $saved = $this->cms->saved(Cms::SETTINGS);
        $values = [];

        foreach ($changes as $key => $change) {
            $values[$key] = $this->builderValues($sections[$key], is_array($saved[$key] ?? null) ? $saved[$key] : [], $change);
        }

        try {
            $this->siteInfo->save($values, McpActor::user());
        } catch (ValidationException $exception) {
            return ['ok' => false, 'errors' => array_map(fn (array $messages): string => (string) ($messages[0] ?? ''), $exception->errors())];
        }

        return ['ok' => true];
    }

    /**
     * @param  array<mixed>  $changes
     * @param  array<string, mixed>  $sections
     * @return array<string, string>
     */
    private function keyErrors(array $changes, array $sections): array
    {
        if ($changes === [] || array_is_list($changes)) {
            return ['settings' => 'Send the settings to change by section, such as {"contact": {"email": "kedai@example.com"}}.'];
        }

        $errors = [];

        foreach ($changes as $key => $change) {
            $section = $sections[$key] ?? null;

            if ($section === null) {
                $errors[$key] = "There is no settings section {$key}. Sections: ".implode(', ', array_keys($sections)).'.';
            } elseif (! $section instanceof SettingsSection || ! SettingsInfo::editable($section)) {
                $errors[$key] = "The {$key} settings can only be changed on the settings screen in the panel.";
            } elseif (! is_array($change) || ($change !== [] && array_is_list($change))) {
                $errors[$key] = "Send the {$key} settings as an object of field names and values.";
            } else {
                $names = array_map(fn (Field $field): string => $field->name, $section->builderFields());

                foreach (array_diff(array_keys($change), $names) as $unknown) {
                    $errors["{$key}.{$unknown}"] = "{$key} has no setting {$unknown}. Its settings: ".implode(', ', $names).'.';
                }
            }
        }

        return $errors;
    }

    /**
     * The section's stored values with the change on top, as the builder's
     * fields hold them; a picture given as a path becomes its address.
     *
     * @param  array<string, mixed>  $saved
     * @param  array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private function builderValues(SettingsSection $section, array $saved, array $change): array
    {
        $merged = [...PageContent::merge($this->cms->definition(Cms::SETTINGS), $section, $saved), ...$change];
        $values = $section->toBuilderValues($merged);

        foreach ($section->builderFields() as $field) {
            $picture = $values[$field->name] ?? null;

            if ($field->type === 'image' && is_string($picture) && $picture !== '' && preg_match('~^(https?://|/)~i', $picture) !== 1) {
                $values[$field->name] = cms_image($picture);
            }
        }

        return $values;
    }
}
