<?php

namespace Vortechron\FilamentCms\Mcp;

use Illuminate\Support\Arr;
use Vortechron\FilamentBlockEditor\Design\Kit;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Models\Document;

/**
 * Saves the design kit for the MCP server. The agent sends only the parts
 * it changes: they are merged over the stored kit (a sent `custom_colors`
 * replaces the whole list), checked with the kit's rules, and saved
 * through DocumentStore. A sent value the kit would drop or replace (a
 * wrong colour, an unknown key) refuses the whole save, so the agent never
 * thinks a value was kept when it was not.
 */
final class KitWriter
{
    public function __construct(private readonly DocumentStore $store) {}

    /**
     * @param  array<mixed>  $changes
     * @return array{ok: bool, errors?: array<string, string>, kit?: array<string, mixed>}
     */
    public function save(array $changes): array
    {
        $errors = Input::errors(['kit' => $changes], Kit::rules('kit'));

        if ($errors !== []) {
            return ['ok' => false, 'errors' => self::withoutPrefix($errors)];
        }

        $document = Document::firstOfKind(Document::KIT) ?? new Document(['kind' => Document::KIT, 'title' => CmsKind::Kit->label()]);
        $kit = self::merge(Kit::normalize(is_array($document->content) ? $document->content : []), $changes);
        $errors = self::dropped($changes, Kit::normalize($kit));

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $result = $this->store->save($document, $kit, McpActor::user(), developer: true);

        return $result['ok'] ? [...$result, 'kit' => Kit::normalize($document->content)] : $result;
    }

    /**
     * @param  array<string, mixed>  $kit
     * @param  array<mixed>  $changes
     * @return array<string, mixed>
     */
    private static function merge(array $kit, array $changes): array
    {
        $merged = array_replace_recursive($kit, Arr::except($changes, ['custom_colors', 'version']));

        return array_key_exists('custom_colors', $changes) ? [...$merged, 'custom_colors' => $changes['custom_colors']] : $merged;
    }

    /**
     * Each sent value that the cleaned kit does not hold as sent.
     *
     * @param  array<mixed>  $changes
     * @param  array<string, mixed>  $clean
     * @return array<string, string>
     */
    private static function dropped(array $changes, array $clean): array
    {
        $errors = [];

        foreach (Arr::dot(Arr::except($changes, ['custom_colors', 'version'])) as $key => $value) {
            if ($value === []) {
                continue;
            }

            if (! Arr::has($clean, (string) $key)) {
                $errors[$key] = "{$key} is not a kit setting. See the Design kit part of page_format.";
            } elseif (! self::same(Arr::get($clean, (string) $key), $value)) {
                $errors[$key] = json_encode($value)." is not allowed for {$key}. See the Design kit part of page_format.";
            }
        }

        foreach (is_array($changes['custom_colors'] ?? null) ? array_values($changes['custom_colors']) : [] as $index => $color) {
            $kept = collect($clean['custom_colors'])->contains(fn (array $kept): bool => is_array($color)
                && $kept['key'] === ($color['key'] ?? null) && self::same($kept['value'], $color['value'] ?? null));

            if (! $kept) {
                $errors["custom_colors.{$index}"] = 'A custom colour needs a key of a-z, 0-9 and dashes (1 to 30 characters, used once, not a fixed colour name) and a #rrggbb value.';
            }
        }

        return $errors;
    }

    private static function same(mixed $kept, mixed $sent): bool
    {
        return is_string($kept) && is_scalar($sent) ? strtolower($kept) === strtolower(trim((string) $sent)) : $kept === $sent;
    }

    /**
     * @param  array<string, string>  $errors
     * @return array<string, string>
     */
    private static function withoutPrefix(array $errors): array
    {
        $clean = [];

        foreach ($errors as $key => $message) {
            $clean[$key === 'kit' ? 'kit' : substr($key, 4)] = $message;
        }

        return $clean;
    }
}
