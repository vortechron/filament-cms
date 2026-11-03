<?php

namespace Vortechron\FilamentCms\Entries;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;

/**
 * Saves an entry: its title, address and publish switch here, its fields
 * and body through DocumentStore (the one save path), in the same save.
 *
 *     app(EntryStore::class)->save(new Entry(['entry_type' => 'product']), [
 *         'title' => 'Kopi Kampung',
 *         'slug' => null,              // optional; empty: made from the title
 *         'is_published' => true,      // optional
 *         'fields' => ['price' => 'RM 12'],   // optional; the stored fields stay when left out
 *         'sections' => [...],         // optional, for a type with a body
 *     ], $user);
 *
 * The address is `{type path}/{slug}`. A new entry's slug is made from its
 * title; after that it stays when the title changes, until a new slug is
 * given (or an empty one, which makes it from the title again). A slug
 * another document already uses gets "-2", "-3"... A save also moves the
 * entry to its type's current path.
 */
final class EntryStore
{
    private const MAX_SLUG = 80;

    public function __construct(private readonly DocumentStore $documents) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function save(Entry $entry, array $data, ?Authenticatable $user, ?bool $developer = null): array
    {
        $type = $entry->definition();

        if ($type === null) {
            return ['ok' => false, 'errors' => ['entry_type' => __('filament-cms::entries.unknown_type')]];
        }

        $data = [...$data, 'title' => trim((string) ($data['title'] ?? $entry->title ?? ''))];
        $errors = $this->errors($data);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $entry->title = $data['title'];
        $entry->path = $type->getPath() === null ? null : $this->path($entry, $type, $data);

        if (array_key_exists('is_published', $data)) {
            $entry->is_published = (bool) $data['is_published'];
        }

        if (! $entry->exists) {
            $entry->sort = (int) Entry::query()->ofType($type->key)->max('sort') + 1;
        }

        return $this->documents->save($entry, array_intersect_key($data, array_flip(['fields', 'sections'])), $user, $developer);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function errors(array $data): array
    {
        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'is_published' => ['sometimes', 'boolean'],
        ], attributes: ['title' => __('filament-cms::entries.title')]);

        return array_map(fn (array $messages): string => (string) ($messages[0] ?? ''), $validator->errors()->messages());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function path(Entry $entry, EntryType $type, array $data): string
    {
        $slug = match (true) {
            filled($data['slug'] ?? null) => (string) $data['slug'],
            array_key_exists('slug', $data) || $entry->path === null => $data['title'],
            default => (string) Str::afterLast($entry->path, '/'),
        };

        $slug = trim(Str::limit(Str::slug($slug), self::MAX_SLUG, ''), '-') ?: $type->key;
        $path = "{$type->getPath()}/{$slug}";

        for ($number = 2; $this->taken($path, $entry); $number++) {
            $path = "{$type->getPath()}/{$slug}-{$number}";
        }

        return $path;
    }

    /**
     * Any other document with this address: page and entry addresses share one column.
     */
    private function taken(string $path, Entry $entry): bool
    {
        return Document::query()
            ->where('path', $path)
            ->when($entry->exists, fn ($query) => $query->whereKeyNot($entry->getKey()))
            ->exists();
    }
}
