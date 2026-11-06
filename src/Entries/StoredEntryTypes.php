<?php

namespace Vortechron\FilamentCms\Entries;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Models\CmsContent;
use Vortechron\FilamentCms\Models\Entry;

/**
 * Entry types made in the panel (the Content types screen) instead of in
 * code: no migration and no deploy for a new one. They are kept as one
 * content key in `cms_contents` (with its versions in `cms_revisions`),
 * and EntryTypes serves them next to the code types, which win on the
 * same key or path.
 *
 * One stored type:
 *
 *     ['key' => 'product', 'label' => 'Product', 'plural_label' => 'Products',
 *      'icon' => 'heroicon-o-cube', 'path' => '/products', 'body' => false, 'sort' => 'newest',
 *      'fields' => [['name' => 'price', 'label' => 'Price', 'type' => 'number', 'options' => ''],
 *                   ['name' => 'category', 'label' => 'Category', 'type' => 'entry', 'options' => '', 'target' => 'category']]]
 *
 * An "entry" field links to one entry of another type (its `target`), like
 * an item to its category: a select of that type's entries, by id.
 */
final class StoredEntryTypes
{
    public const KEY = 'entry-types';

    /**
     * The field kinds the screen offers.
     */
    public const FIELD_TYPES = ['text', 'textarea', 'number', 'select', 'toggle', 'link', 'image', 'entry'];

    /**
     * The icons the screen offers.
     */
    public const ICONS = [
        'heroicon-o-rectangle-stack', 'heroicon-o-cube', 'heroicon-o-shopping-bag', 'heroicon-o-tag',
        'heroicon-o-calendar-days', 'heroicon-o-newspaper', 'heroicon-o-photo', 'heroicon-o-user-group',
        'heroicon-o-map-pin', 'heroicon-o-question-mark-circle', 'heroicon-o-star', 'heroicon-o-wrench-screwdriver',
    ];

    /**
     * The saved definitions, as stored; none while the table is missing.
     *
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return self::read() ?? [];
    }

    /**
     * Every saved type that still builds, by key, or null when the database
     * can not be read yet (before the migrations, or while it is down). One
     * that no longer builds (a path the site now uses) is left out and logged.
     *
     * @return array<string, EntryType>|null
     */
    public static function load(): ?array
    {
        $definitions = self::read();

        if ($definitions === null) {
            return null;
        }

        $types = [];

        foreach ($definitions as $definition) {
            try {
                $type = self::build($definition);
                $types[$type->key] = $type;
            } catch (InvalidArgumentException $exception) {
                Log::warning('A stored entry type was skipped: '.$exception->getMessage());
            }
        }

        return $types;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private static function read(): ?array
    {
        try {
            $content = CmsContent::query()->where('key', self::KEY)->value('content');
        } catch (QueryException) {
            return null;
        }

        $content = is_string($content) ? json_decode($content, true) : $content;
        $types = is_array($content) && is_array($content['types'] ?? null) ? $content['types'] : [];

        return array_values(array_filter($types, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $definition
     *
     * @throws InvalidArgumentException
     */
    public static function build(array $definition): EntryType
    {
        $type = EntryType::make((string) ($definition['key'] ?? ''))
            ->label((string) ($definition['label'] ?? ''))
            ->icon(is_string($definition['icon'] ?? null) && $definition['icon'] !== '' ? $definition['icon'] : null)
            ->fields(array_map(self::field(...), is_array($definition['fields'] ?? null) ? array_values($definition['fields']) : []))
            ->path(is_string($definition['path'] ?? null) && trim($definition['path']) !== '' ? $definition['path'] : null)
            ->body((bool) ($definition['body'] ?? false))
            ->sort((string) ($definition['sort'] ?? 'newest'));

        if (is_string($definition['plural_label'] ?? null) && trim($definition['plural_label']) !== '') {
            $type->pluralLabel(trim($definition['plural_label']));
        }

        return $type;
    }

    /**
     * Saves one type from the screen: a new one goes last, a saved one
     * (`$savedKey`) keeps its place and its key. Checked with the others
     * (clean()); answers the problem in words, or null once saved.
     *
     * @param  array<string, mixed>  $item  the screen's form state
     * @param  array<string, EntryType>  $codeTypes
     */
    public static function put(array $item, ?string $savedKey, array $codeTypes, ?Authenticatable $user): ?string
    {
        $items = self::definitions();
        $index = $savedKey === null ? false : array_search($savedKey, array_column($items, 'key'), true);

        if ($savedKey !== null && $index === false) {
            return __('filament-cms::types.gone');
        }

        if ($index === false) {
            $items[] = $item;
        } else {
            $items[$index] = [...$item, 'key' => $savedKey];
        }

        return self::store($items, $codeTypes, $user);
    }

    /**
     * Removes one type; refused while it still has entries (clean()).
     *
     * @param  array<string, EntryType>  $codeTypes
     */
    public static function remove(string $key, array $codeTypes, ?Authenticatable $user): ?string
    {
        $items = array_values(array_filter(self::definitions(), fn (array $item): bool => ($item['key'] ?? null) !== $key));

        foreach ($items as $item) {
            foreach (is_array($item['fields'] ?? null) ? $item['fields'] : [] as $field) {
                if (($field['type'] ?? null) === 'entry' && ($field['target'] ?? null) === $key) {
                    return __('filament-cms::types.linked_from', ['type' => (string) ($item['label'] ?? $item['key'] ?? ''), 'field' => (string) ($field['label'] ?? $field['name'] ?? '')]);
                }
            }
        }

        return self::store($items, $codeTypes, $user);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, EntryType>  $codeTypes
     */
    private static function store(array $items, array $codeTypes, ?Authenticatable $user): ?string
    {
        [$definitions, $problem] = self::clean($items, $codeTypes);

        if ($problem !== null) {
            return $problem;
        }

        app(ContentStore::class)->save(self::KEY, ['types' => $definitions], $user);
        app(EntryTypes::class)->flushStored();

        return null;
    }

    /**
     * The screen's items made into definitions, checked: either the
     * definitions or the first problem, in words for the screen.
     *
     * @param  array<mixed>  $items  the form's repeater items
     * @param  array<string, EntryType>  $codeTypes  the types made in code
     * @return array{0: list<array<string, mixed>>, 1: ?string}
     */
    public static function clean(array $items, array $codeTypes): array
    {
        $definitions = [];
        $paths = array_filter(array_map(fn (EntryType $type): ?string => $type->getPath(), $codeTypes));

        foreach (array_values(array_filter($items, 'is_array')) as $item) {
            $definition = self::definition($item);
            $key = $definition['key'];

            if (isset($definitions[$key])) {
                return [[], __('filament-cms::types.duplicate', ['key' => $key])];
            }

            if (isset($codeTypes[$key])) {
                return [[], __('filament-cms::types.code_type', ['key' => $key])];
            }

            try {
                $type = self::build($definition);
            } catch (InvalidArgumentException $exception) {
                return [[], __('filament-cms::types.invalid', ['type' => $definition['label'] !== '' ? $definition['label'] : $key, 'error' => $exception->getMessage()])];
            }

            if ($type->getPath() !== null && in_array($type->getPath(), $paths, true)) {
                return [[], __('filament-cms::types.path_taken', ['path' => $type->getPath()])];
            }

            $paths[] = $type->getPath();
            $definition['path'] = $type->getPath();
            $definitions[$key] = $definition;
        }

        $problem = self::linkProblem($definitions, $codeTypes);

        if ($problem !== null) {
            return [[], $problem];
        }

        foreach (self::definitions() as $old) {
            $key = (string) ($old['key'] ?? '');
            $count = isset($definitions[$key]) ? 0 : Entry::query()->where('entry_type', $key)->count();

            if ($count > 0) {
                return [[], __('filament-cms::types.has_entries', ['type' => (string) ($old['label'] ?? $key), 'count' => $count])];
            }
        }

        return [array_values($definitions), null];
    }

    /**
     * The first link field that links to no type, in words, or null.
     *
     * @param  array<string, array<string, mixed>>  $definitions
     * @param  array<string, EntryType>  $codeTypes
     */
    private static function linkProblem(array $definitions, array $codeTypes): ?string
    {
        foreach ($definitions as $definition) {
            foreach ($definition['fields'] as $field) {
                if ($field['type'] === 'entry' && ! isset($definitions[$field['target']]) && ! isset($codeTypes[$field['target']])) {
                    return __('filament-cms::types.link_target', [
                        'field' => $field['label'] !== '' ? $field['label'] : $field['name'],
                        'type' => $definition['label'] !== '' ? $definition['label'] : $definition['key'],
                    ]);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function definition(array $item): array
    {
        $fields = [];

        foreach (array_values(array_filter(is_array($item['fields'] ?? null) ? $item['fields'] : [], 'is_array')) as $field) {
            $type = in_array($field['type'] ?? null, self::FIELD_TYPES, true) ? $field['type'] : 'text';
            $fields[] = [
                'name' => Str::lower(trim((string) ($field['name'] ?? ''))),
                'label' => trim((string) ($field['label'] ?? '')),
                'type' => $type,
                ...(array_key_exists('translatable', $field) && is_bool($field['translatable']) ? ['translatable' => $field['translatable']] : []),
                'options' => $type === 'select' ? trim((string) ($field['options'] ?? '')) : '',
                ...($type === 'entry' ? ['target' => Str::lower(trim((string) ($field['target'] ?? '')))] : []),
            ];
        }

        return [
            'key' => Str::lower(trim((string) ($item['key'] ?? ''))),
            'label' => trim((string) ($item['label'] ?? '')),
            'plural_label' => trim((string) ($item['plural_label'] ?? '')),
            'icon' => in_array($item['icon'] ?? null, self::ICONS, true) ? $item['icon'] : self::ICONS[0],
            'path' => trim((string) ($item['path'] ?? '')),
            'body' => (bool) ($item['body'] ?? false),
            'sort' => in_array($item['sort'] ?? null, EntryType::SORTS, true) ? $item['sort'] : 'newest',
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function field(array $field): Field
    {
        $name = (string) ($field['name'] ?? '');

        $made = match ($field['type'] ?? 'text') {
            'text' => Field::text($name),
            'textarea' => Field::textarea($name),
            'number' => Field::number($name),
            'select' => Field::select($name)->options(self::options((string) ($field['options'] ?? ''))),
            'toggle' => Field::toggle($name),
            'link' => Field::link($name),
            'image' => Field::image($name),
            'entry' => self::link($name, (string) ($field['target'] ?? '')),
            default => throw new InvalidArgumentException('An entry field is one of '.implode(', ', self::FIELD_TYPES).". Got [{$field['type']}]."),
        };

        if (array_key_exists('translatable', $field) && is_bool($field['translatable'])) {
            $made->translatable($field['translatable']);
        }
        $label = trim((string) ($field['label'] ?? ''));

        return $label === '' ? $made : $made->label($label);
    }

    /**
     * A link field: a select of every entry of the target type, by id, with
     * an empty first choice for none. Every entry, never the first few, so a
     * link is never emptied for being past a limit; it is meant for short
     * lists such as categories. A link to an entry that is gone (or of
     * another type) is left empty when the entry is next saved.
     */
    private static function link(string $name, string $target): Field
    {
        return Field::select($name)->options([['value' => '', 'label' => ''], ...self::linkChoices($target)])->default('');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function linkChoices(string $target): array
    {
        try {
            return Entry::query()
                ->ofType($target)
                ->orderBy('title')
                ->orderBy('id')
                ->get(['id', 'title'])
                ->map(fn (Entry $entry): array => ['value' => (string) $entry->getKey(), 'label' => (string) $entry->title])
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * A select field's choices: one per line, stored under its slug.
     *
     * @return array<string, string>
     */
    public static function options(string $lines): array
    {
        $options = [];

        foreach (preg_split('/\R/', $lines) ?: [] as $line) {
            $line = trim($line);
            $value = Str::slug($line, '_');

            if ($line !== '' && $value !== '' && ! isset($options[$value])) {
                $options[$value] = $line;
            }
        }

        return $options;
    }
}
