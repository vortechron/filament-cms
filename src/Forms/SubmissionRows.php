<?php

namespace Vortechron\FilamentCms\Forms;

use Vortechron\FilamentBlockEditor\Forms\FormDefinition;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\FormSubmission;

/**
 * A submission's values as rows of a label and plain text, for the email,
 * the "Borang" screen and the CSV. Labels come from the stored form when
 * it is known, else from the field names. Values the form no longer has
 * come last, under their names.
 */
final class SubmissionRows
{
    /**
     * A saved submission's rows, labelled from its stored form when the
     * document still has it.
     *
     * @return list<array{name: string, label: string, value: string}>
     */
    public static function of(FormSubmission $submission): array
    {
        return self::make(self::form($submission), $submission->data ?? []);
    }

    /**
     * The stored form a submission was sent with, or null when the document
     * or the form is gone.
     */
    public static function form(FormSubmission $submission): ?FormDefinition
    {
        $document = $submission->document_id === null ? null : Document::query()->find($submission->document_id);

        return $document === null ? null : app(FormLocator::class)->find($document, $submission->form_key, shownOnly: false)?->form;
    }

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
