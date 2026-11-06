<?php

namespace Vortechron\FilamentCms\Documents;

use InvalidArgumentException;
use Vortechron\FilamentBlockEditor\BlockNote\BlockNoteDocument;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Content\PageContent;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentBlockEditor\Content\TextTranslations;
use Vortechron\FilamentBlockEditor\Forms\FormDefinition;
use Vortechron\FilamentBlockEditor\Forms\FormFields;

/** @internal Recovers historical identities before normalization can replace them. */
final class RestoreIdentities
{
    public function recover(array $snapshot, array $current): array
    {
        // Conversion's schema checks also prevent a restore from silently dropping unknown data.
        TextTranslations::extract($snapshot, $snapshot);
        $snapshot = PageValue::upgrade($snapshot);
        $current = PageValue::upgrade($current);
        $currentBlocks = $this->indexBlocks($current);
        $this->indexBlocks($snapshot);
        $snapshot['sections'] = $this->collection($snapshot['sections'] ?? [], $current['sections'] ?? [], 'id', function (array $section, array $stored) use ($currentBlocks): array {
            if (($section['type'] ?? null) === 'rich') {
                $section['data']['document'] = $this->blocks($section['data']['document'] ?? [], $stored['data']['document'] ?? [], $currentBlocks);
            }

            return $section;
        });

        return $snapshot;
    }

    private function blocks(array $blocks, array $stored, array $currentBlocks): array
    {
        return $this->collection($blocks, $stored, 'id', function (array $block, array $previous) use ($currentBlocks): array {
            $previous = $currentBlocks[$block['id'] ?? ''] ?? $previous;
            if ($previous !== [] && ! $this->compatible($block, $previous, null)) {
                throw new InvalidArgumentException('A historical block identity has a different content type.');
            }
            if (($block['type'] ?? null) === 'ownBlock') {
                $definition = app(BlockRegistry::class)->get($block['props']['kind'] ?? '');
                if ($definition === null || ($previous !== [] && ($previous['props']['kind'] ?? null) !== $block['props']['kind'])) {
                    throw new InvalidArgumentException('Unsupported historical custom block.');
                }
                $values = $definition->clean($this->decode($block['props']['values'] ?? '{}'));
                $old = $previous === [] ? [] : $definition->clean($this->decode($previous['props']['values'] ?? '{}'));
                $block['props']['values'] = json_encode($this->fields($values, $old, $definition->toArray()['fields']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (($block['type'] ?? null) === 'table') {
                $rows = $block['content']['rows'] ?? [];
                $flatten = static function (array $rows): array {
                    $cells = [];
                    foreach ($rows as $row) {
                        foreach ($row['cells'] ?? [] as $cell) {
                            if (! is_array($cell) || (! array_is_list($cell) && ($cell['type'] ?? null) !== 'tableCell')) {
                                throw new InvalidArgumentException('Unsupported historical table cell.');
                            }
                            $cells[] = array_is_list($cell) ? ['type' => 'tableCell', 'content' => $cell] : $cell;
                        }
                    }

                    return $cells;
                };
                $cells = $this->collection($flatten($rows), $flatten($previous['content']['rows'] ?? []), '_id', fn (array $cell): array => $cell);
                $index = 0;
                foreach ($rows as &$row) {
                    foreach (array_keys($row['cells'] ?? []) as $key) {
                        $row['cells'][$key] = $cells[$index++];
                    }
                }
                unset($row);
                $block['content']['rows'] = $rows;
            }
            if (($block['type'] ?? null) === 'form') {
                $block['props']['form'] = json_encode($this->form($this->decode($block['props']['form'] ?? '{}'), $this->decode($previous['props']['form'] ?? '{}')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (isset($block['children'])) {
                $block['children'] = $this->blocks($block['children'], $previous['children'] ?? [], $currentBlocks);
            }

            return $block;
        });
    }

    /** Block IDs keep their meaning when a block moves between sections or nested groups. */
    private function indexBlocks(array $value): array
    {
        $index = [];
        $collect = function (array $blocks) use (&$collect, &$index): void {
            foreach ($blocks as $block) {
                if (isset($block['id'])) {
                    $id = BlockNoteDocument::blockId($block['id']);
                    if ($id === null || $id !== $block['id'] || isset($index[$id])) {
                        throw new InvalidArgumentException('Invalid or duplicate historical block identity.');
                    }
                    $index[$id] = $block;
                }
                $collect($block['children'] ?? []);
            }
        };
        foreach ($value['sections'] ?? [] as $section) {
            $collect($section['data']['document'] ?? []);
        }

        return $index;
    }

    private function fields(array $values, array $stored, array $fields): array
    {
        foreach ($fields as $field) {
            if ($field['type'] === 'list' && is_array($values[$field['name']] ?? null)) {
                $values[$field['name']] = $this->collection($values[$field['name']], $stored[$field['name']] ?? [], '_id', fn (array $row, array $previous): array => $this->fields($row, $previous, $field['fields'] ?? []));
            }
        }

        return $values;
    }

    private function form(array $form, array $stored): array
    {
        $fields = [];
        foreach ($form['fields'] ?? [] as $field) {
            if (! in_array($field['type'] ?? null, FormDefinition::FIELD_TYPES, true)) {
                throw new InvalidArgumentException('Unsupported historical form field.');
            }
            $clean = FormFields::clean([$field])[0];
            if (isset($field['id'])) {
                $clean['id'] = $field['id'];
            } else {
                unset($clean['id']);
            }
            $fields[] = $clean;
        }
        $form['fields'] = $this->collection($fields, $stored['fields'] ?? [], 'id', function (array $field, array $previous): array {
            if (isset($field['options'])) {
                $wrap = static fn (array $item): array => ['value' => $item[0], ...($item[1] === null ? [] : ['_id' => $item[1]])];
                $options = array_map(fn ($option, $index): array => $wrap([$option, $field['option_ids'][$index] ?? null]), $field['options'], array_keys($field['options']));
                $baseline = array_map(fn ($option, $index): array => $wrap([$option, $previous['option_ids'][$index] ?? null]), $previous['options'] ?? [], array_keys($previous['options'] ?? []));
                $options = $this->collection($options, $baseline, '_id', fn (array $option): array => $option);
                $field['options'] = array_column($options, 'value');
                $field['option_ids'] = array_map(fn (array $option): string => $option['_id'] ?? PageContent::newId(), $options);
            }

            return $field;
        }, 'name');

        return $form;
    }

    /** Exact unique survivors first; only a single remaining compatible pair may inherit an ID. */
    private function collection(array $items, array $stored, string $identity, callable $recover, ?string $discriminator = null): array
    {
        $matches = [];
        $used = [];
        $seen = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Unsupported historical collection.');
            }
            if (isset($item[$identity])) {
                $id = $item[$identity];
                if (! is_string($id) || preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id) !== 1 || isset($seen[$id])) {
                    throw new InvalidArgumentException('Invalid or duplicate historical identity.');
                }
                $seen[$id] = true;
                foreach ($stored as $oldIndex => $previous) {
                    if (($previous[$identity] ?? null) === $id) {
                        if (! $this->compatible($item, $previous, $discriminator, $identity === 'id')) {
                            throw new InvalidArgumentException('A historical identity has a different content type.');
                        }
                        $matches[$index] = $oldIndex;
                        $used[$oldIndex] = true;
                        break;
                    }
                }
                $matches[$index] ??= null;
            }
        }
        foreach ($items as $index => $item) {
            if (array_key_exists($index, $matches)) {
                continue;
            }
            $candidates = array_keys(array_filter($stored, fn (array $previous, int $oldIndex): bool => ! isset($used[$oldIndex]) && $this->compatible($item, $previous, $discriminator, $identity === 'id') && $this->withoutIds($item, $identity) === $this->withoutIds($previous, $identity), ARRAY_FILTER_USE_BOTH));
            $duplicates = array_filter($items, fn (array $other, int $otherIndex): bool => ! array_key_exists($otherIndex, $matches) && $this->withoutIds($other, $identity) === $this->withoutIds($item, $identity), ARRAY_FILTER_USE_BOTH);
            if (count($candidates) === 1 && count($duplicates) === 1) {
                $matches[$index] = $candidates[0];
                $used[$candidates[0]] = true;
            }
        }
        foreach ($items as $index => $item) {
            if (array_key_exists($index, $matches)) {
                continue;
            }
            $candidates = array_keys(array_filter($stored, fn (array $previous, int $oldIndex): bool => ! isset($used[$oldIndex]) && $this->compatible($item, $previous, $discriminator, $identity === 'id'), ARRAY_FILTER_USE_BOTH));
            if ($candidates === []) {
                $matches[$index] = null;

                continue;
            }
            $others = array_filter($items, fn (array $other, int $otherIndex): bool => ! array_key_exists($otherIndex, $matches) && $this->compatible($other, $stored[$candidates[0]], $discriminator, $identity === 'id'), ARRAY_FILTER_USE_BOTH);
            if (count($candidates) !== 1 || count($others) !== 1) {
                throw new InvalidArgumentException('Ambiguous historical identities.');
            }
            $matches[$index] = $candidates[0];
            $used[$candidates[0]] = true;
        }
        foreach ($items as $index => &$item) {
            $previous = isset($matches[$index]) ? $stored[$matches[$index]] : [];
            if (! isset($item[$identity]) && isset($previous[$identity])) {
                $item[$identity] = $previous[$identity];
            }
            $item = $recover($item, $previous);
        }
        unset($item);

        return array_values($items);
    }

    private function compatible(array $item, array $previous, ?string $discriminator, bool $typed = true): bool
    {
        return ($discriminator === null || ($item[$discriminator] ?? null) === ($previous[$discriminator] ?? null))
            && (! $typed || (($item['type'] ?? null) === ($previous['type'] ?? null)
                && (($item['type'] ?? null) !== 'ownBlock' || ($item['props']['kind'] ?? null) === ($previous['props']['kind'] ?? null))));
    }

    private function withoutIds(array $value, string $identity): array
    {
        unset($value[$identity], $value['_id']);
        foreach ($value as &$child) {
            if (is_array($child)) {
                $child = $this->withoutIds($child, '_id');
            }
        }
        unset($child);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function decode(string $json): array
    {
        $value = json_decode($json, true);
        if (! is_array($value)) {
            throw new InvalidArgumentException('Unsupported historical JSON.');
        }

        return $value;
    }
}
