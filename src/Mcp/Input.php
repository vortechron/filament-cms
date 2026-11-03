<?php

namespace Vortechron\FilamentCms\Mcp;

use Illuminate\Support\Facades\Validator;

/**
 * Checks what an agent sent to a tool. Every tool checks its input here
 * before it reads or writes anything.
 */
final class Input
{
    /**
     * The first message of each failing rule, by key; [] when it passes.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array<string, string>
     */
    public static function errors(array $input, array $rules, array $messages = []): array
    {
        $validator = Validator::make($input, $rules, $messages);

        return $validator->fails()
            ? array_map(fn (array $messages): string => (string) ($messages[0] ?? ''), $validator->errors()->messages())
            : [];
    }

    /**
     * A value an agent may send as JSON or as a JSON string: the decoded
     * value, or null when the string is not JSON.
     */
    public static function json(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
