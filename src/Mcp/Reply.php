<?php

namespace Vortechron\FilamentCms\Mcp;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * What every MCP tool answers: JSON, as text and as structured content.
 *
 * - read(): what a read tool found;
 * - saved(): a write that went through, `{ok: true, errors: {}, ...}`;
 * - refused(): a write that changed nothing, `{ok: false, errors: {key: words}}`,
 *   marked as an error so the agent sees it.
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
     * A refusal with one message.
     */
    public static function refuse(string $key, string $message): ResponseFactory
    {
        return self::refused([$key => $message]);
    }
}
