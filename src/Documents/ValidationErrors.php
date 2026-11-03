<?php

namespace Vortechron\FilamentCms\Documents;

use Illuminate\Support\Facades\Validator;

/**
 * The first message of each failing rule, keyed without the outer name
 * the value was checked under (`value.sections.0.id` is `sections.0.id`).
 */
final class ValidationErrors
{
    /**
     * @param  array<string, mixed>  $rules  rules for `$name`
     * @return array<string, string>
     */
    public static function of(string $name, mixed $value, array $rules): array
    {
        $validator = Validator::make([$name => $value], $rules);
        $errors = [];

        foreach ($validator->errors()->messages() as $key => $messages) {
            $errors[$key === $name ? $key : substr($key, strlen($name) + 1)] = (string) ($messages[0] ?? '');
        }

        return $errors;
    }
}
