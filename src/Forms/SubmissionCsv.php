<?php

namespace Vortechron\FilamentCms\Forms;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Vortechron\FilamentBlockEditor\Forms\FormDefinition;
use Vortechron\FilamentCms\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Vortechron\FilamentCms\Models\FormSubmission;

/**
 * The "Borang" rows as a CSV file for a spreadsheet: when it came, the
 * form, its status and page, then one column per field (labelled from the
 * stored form). UTF-8 with a byte order mark so Excel reads Malay words
 * and emoji. A value that a spreadsheet would run as a formula (it starts
 * with = + - @ or a tab) gets a ' in front.
 */
final class SubmissionCsv
{
    /**
     * @var array<string, FormDefinition|null>
     */
    private array $forms = [];

    /**
     * @param  Builder<FormSubmission>  $query
     */
    public function download(Builder $query): StreamedResponse
    {
        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $columns = $this->columns($query);

            self::line($out, [
                __('filament-cms::forms.received'), __('filament-cms::forms.form'), __('filament-cms::forms.status'), __('filament-cms::forms.page'),
                ...array_values($columns),
            ]);

            foreach ($query->clone()->lazyById(200) as $submission) {
                $values = array_column($this->rows($submission), 'value', 'name');

                self::line($out, [
                    $submission->created_at?->toDateTimeString() ?? '',
                    FormSubmissionResource::formName($submission),
                    FormSubmissionResource::statusLabel($submission->status),
                    (string) ($submission->meta['page'] ?? ''),
                    ...array_map(fn (string $name): string => $values[$name] ?? '', array_keys($columns)),
                ]);
            }

            fclose($out);
        }, 'borang-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Every field name in the rows, in the order first seen, with its label.
     *
     * @param  Builder<FormSubmission>  $query
     * @return array<string, string>
     */
    private function columns(Builder $query): array
    {
        $columns = [];

        foreach ($query->clone()->lazyById(200) as $submission) {
            foreach ($this->rows($submission) as $row) {
                $columns[$row['name']] ??= $row['label'];
            }
        }

        return $columns;
    }

    /**
     * @return list<array{name: string, label: string, value: string}>
     */
    private function rows(FormSubmission $submission): array
    {
        $key = $submission->document_id.'|'.$submission->form_key;

        if (! array_key_exists($key, $this->forms)) {
            $this->forms[$key] = SubmissionRows::form($submission);
        }

        return SubmissionRows::make($this->forms[$key], $submission->data ?? []);
    }

    /**
     * @param  resource  $out
     * @param  list<string>  $cells
     */
    private static function line($out, array $cells): void
    {
        fputcsv($out, array_map(self::safe(...), $cells), ',', '"', '', "\r\n");
    }

    private static function safe(string $cell): string
    {
        return preg_match('/^[=+\-@\t\r]/', $cell) === 1 ? "'".$cell : $cell;
    }
}
