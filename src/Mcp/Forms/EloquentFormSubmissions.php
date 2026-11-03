<?php

namespace Vortechron\FilamentCms\Mcp\Forms;

use Vortechron\FilamentCms\Mcp\Contracts\McpFormSubmissions;
use Vortechron\FilamentCms\Models\FormSubmission;

/**
 * Form submissions in `cms_form_submissions`, through Models\FormSubmission.
 * The meta keys about the visitor's address are left out.
 */
final class EloquentFormSubmissions implements McpFormSubmissions
{
    public function list(array $filters): array
    {
        $submissions = FormSubmission::query()
            ->when($filters['status'] ?? null, fn ($query, mixed $status) => $query->where('status', (string) $status))
            ->when($filters['form_key'] ?? null, fn ($query, mixed $key) => $query->where('form_key', (string) $key))
            ->when($filters['document_id'] ?? null, fn ($query, mixed $id) => $query->where('document_id', (int) $id))
            ->when($filters['before_id'] ?? null, fn ($query, mixed $id) => $query->where('id', '<', (int) $id))
            ->orderByDesc('id')
            ->limit((int) ($filters['limit'] ?? 20))
            ->get();

        return [
            'submissions' => $submissions->map(fn (FormSubmission $submission): array => [
                'id' => $submission->getKey(),
                'document_id' => $submission->getAttribute('document_id'),
                'form_key' => $submission->getAttribute('form_key'),
                'form_name' => $submission->getAttribute('form_name'),
                'status' => $submission->getAttribute('status'),
                'data' => $submission->getAttribute('data'),
                'meta' => array_filter((array) $submission->getAttribute('meta'), fn (mixed $value, int|string $key): bool => preg_match('/ip/i', (string) $key) !== 1, ARRAY_FILTER_USE_BOTH),
                'created_at' => $submission->getAttribute('created_at')?->toIso8601String(),
            ])->all(),
            'counts' => array_map(fn (string $status): int => FormSubmission::query()->where('status', $status)->count(), array_combine(self::STATUSES, self::STATUSES)),
        ];
    }

    public function update(int $id, string $status): array
    {
        $submission = FormSubmission::query()->find($id);

        if ($submission === null) {
            return ['ok' => false, 'errors' => ['id' => "There is no form submission {$id}."]];
        }

        $submission->forceFill(['status' => $status])->save();

        return ['ok' => true];
    }
}
