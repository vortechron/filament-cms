<?php

namespace Vortechron\FilamentCms\Mcp;

use Closure;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * What every MCP tool answers: JSON, as text and as structured content.
 *
 * - read(): what a read tool found;
 * - saved(): a write that went through, `{ok: true, errors: {}, ...}`;
 * - refused(): a write that changed nothing, `{ok: false, errors: {key: words}}`,
 *   marked as an error so the agent sees it;
 * - fromResult(): one of those two for a store's `{ok, errors?, ...}`.
 */
final class Reply
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function read(array $data): ResponseFactory
    {
        return Response::structured($data);
    }

    /**
     * @param  array<string, mixed>  $extra  such as `url` or `id`
     */
    public static function saved(array $extra = []): ResponseFactory
    {
        return Response::structured(['ok' => true, 'errors' => (object) [], ...$extra]);
    }

    /**
     * @param  array<string, string>  $errors  plain words, by the key they are about
     * @param  array<string, mixed>  $extra
     */
    public static function refused(array $errors, array $extra = []): ResponseFactory
    {
        $payload = ['ok' => false, 'errors' => $errors === [] ? (object) [] : $errors, ...$extra];

        return Response::make(Response::error((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)))
            ->withStructuredContent($payload);
    }

    /**
     * saved() with what the result holds besides `ok` (then `$extra`), or
     * refused() with its errors. A closure is read only when the write went
     * through, so it can read the saved state.
     *
     * @param  array{ok: bool, errors?: array<string, string>}  $result
     * @param  array<string, mixed>|Closure(): array<string, mixed>  $extra
     */
    public static function fromResult(array $result, array|Closure $extra = []): ResponseFactory
    {
        if (($result['ok'] ?? false) !== true) {
            return self::refused($result['errors'] ?? []);
        }

        return self::saved([...array_diff_key($result, ['ok' => true, 'errors' => true]), ...($extra instanceof Closure ? $extra() : $extra)]);
    }

    /**
     * A refusal with one message.
     */
    public static function refuse(string $key, string $message): ResponseFactory
    {
        return self::refused([$key => $message]);
    }
}
