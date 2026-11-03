<?php

namespace Vortechron\FilamentCms\Mcp\Entries;

use Illuminate\Contracts\Auth\Authenticatable;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Entries\EntryValues;
use Vortechron\FilamentCms\Mcp\Contracts\McpEntries;
use Vortechron\FilamentCms\Models\Entry;

/**
 * The site's own entries for the MCP server: the types registered in
 * EntryTypes, and entries read from `Models\Entry`.
 */
final class RegisteredEntries implements McpEntries
{
    public function __construct(private readonly EntryTypes $types) {}

    public function available(): bool
    {
        return $this->types->all() !== [];
    }

    public function types(): array
    {
        return array_values(array_map(fn (EntryType $type): array => [
            'key' => $type->key,
            'label' => $type->getLabel(),
            'plural_label' => $type->getPluralLabel(),
            'path' => $type->getPath(),
            'body' => $type->hasBody(),
            'sort' => $type->getSort(),
            'fields' => array_map(fn (Field $field): array => $field->toArray(), $type->getFields()),
            // What its card, entry page design and entry bodies may show, as the builder offers them.
            'dynamic_values' => EntryValues::definitions($type, Entry::firstOf($type)),
        ], $this->types->all()));
    }

    public function list(?string $type, int $limit): array
    {
        $definition = $this->types->get($type);

        return Entry::query()
            ->when($type !== null, fn ($query) => $query->ofType((string) $type))
            ->ordered($definition?->getSort() ?? 'newest')
            ->limit($limit)
            ->get()
            ->map(fn (Entry $entry): array => self::entry($entry))
            ->all();
    }

    /**
     * Saves through EntryStore as a developer: the title, slug, published
     * switch and order here, the fields (cleaned by the type's fields) and
     * the body through DocumentStore, kept as a version.
     */
    public function save(array $entry, ?Authenticatable $user): array
    {
        $model = isset($entry['id']) ? Entry::query()->find((int) $entry['id']) : new Entry(['entry_type' => $entry['type'] ?? null]);

        if ($model === null) {
            return ['ok' => false, 'errors' => ['id' => "There is no entry {$entry['id']}."]];
        }

        $errors = $this->errors($model, $entry);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $data = array_filter([
            'title' => $entry['title'] ?? null,
            'is_published' => $entry['published'] ?? null,
            'sort' => $entry['sort'] ?? null,
            'fields' => $entry['fields'] ?? null,
            'sections' => $entry['sections'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        if (array_key_exists('slug', $entry)) {
            $data['slug'] = $entry['slug'];
        }

        $result = app(EntryStore::class)->save($model, $data, $user, developer: true);

        if (! $result['ok']) {
            return $result;
        }

        return ['ok' => true, 'id' => $model->getKey(), 'url' => self::entry($model)['url']];
    }

    /**
     * Like the delete button on the type's screen. Its versions are kept.
     */
    public function delete(int $id): array
    {
        $entry = Entry::query()->find($id);

        if ($entry === null) {
            return ['ok' => false, 'errors' => ['id' => "There is no entry {$id}."]];
        }

        $entry->delete();

        return ['ok' => true];
    }

    /**
     * What EntryStore would drop without a word: an unknown type, a field
     * the type does not have, a body for a type without one.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, string>
     */
    private function errors(Entry $model, array $entry): array
    {
        $type = $model->definition();

        if ($type === null) {
            $types = implode(', ', array_keys($this->types->all()));

            return ['type' => 'There is no entry type '.json_encode($model->entry_type).". Types: {$types}."];
        }

        if (isset($entry['type']) && $entry['type'] !== $type->key) {
            return ['type' => "Entry {$model->getKey()} is a {$type->key}; an entry's type cannot change."];
        }

        $names = array_map(fn (Field $field): string => $field->name, $type->getFields());
        $unknown = array_diff(array_keys($entry['fields'] ?? []), $names);
        $errors = [];

        if ($unknown !== []) {
            $errors['fields'] = 'The '.$type->key.' type has no field '.implode(', ', $unknown).'. Its fields: '.(implode(', ', $names) ?: 'none').'.';
        }

        if (isset($entry['sections']) && ! $type->hasBody()) {
            $errors['sections'] = "The {$type->key} type has no body, so its entries have no sections.";
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private static function entry(Entry $entry): array
    {
        return [
            'id' => $entry->getKey(),
            'type' => $entry->entry_type,
            'title' => (string) $entry->title,
            'path' => $entry->path,
            'url' => $entry->definition()?->getPath() === null ? null : $entry->url(),
            'published' => (bool) $entry->is_published,
            'sort' => (int) $entry->sort,
            'fields' => $entry->fieldValues(),
            'sections' => PageValue::normalize(is_array($entry->content) ? $entry->content : [])['sections'],
        ];
    }
}
