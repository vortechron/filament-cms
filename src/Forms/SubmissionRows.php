<?php

namespace Vortechron\FilamentCms\Forms;

use Vortechron\FilamentBlockEditor\Forms\FormDefinition;

/**
 * A submission's values as rows of a label and plain text, for the email,
 * the "Borang" screen and the CSV. Labels come from the stored form when
 * it is known, else from the field names. Values the form no longer has
 * come last, under their names.
 */
final class SubmissionRows
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<array{name: string, label: string, value: string}>
     */
    public static function make(?FormDefinition $form, array $data): array
    {
        $rows = [];

        foreach ($form?->fields() ?? [] as $field) {
            if (array_key_exists($field['name'], $data)) {
                $rows[$field['name']] = self::row($field['name'], $field['label'] !== '' ? $field['label'] : $field['name'], $data[$field['name']]);
            }
        }

        foreach ($data as $name => $value) {
            $rows[$name] ??= self::row((string) $name, (string) $name, $value);
        }

        return array_values($rows);
    }

    /**
     * One value as plain text: a list joined with commas, a tick as yes or
     * no, nothing as ''.
     */
    public static function text(mixed $value): string
    {
        return match (true) {
            is_bool($value) => __($value ? 'filament-cms::forms.yes' : 'filament-cms::forms.no'),
            is_array($value) => implode(', ', array_map(self::text(...), $value)),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    /**
     * @return array{name: string, label: string, value: string}
     */
    private static function row(string $name, string $label, mixed $value): array
    {
        return ['name' => $name, 'label' => $label, 'value' => self::text($value)];
    }
}
