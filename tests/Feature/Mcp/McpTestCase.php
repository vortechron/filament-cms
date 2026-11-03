<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentCms\Mcp\CmsServer;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

/**
 * Calls the MCP server's tools the way an agent does (laravel/mcp's test
 * helpers), and reads their JSON answers.
 */
abstract class McpTestCase extends TestCase
{
    protected bool $lastToolFailed = false;

    protected function setUp(): void
    {
        parent::setUp();

        TestResponse::macro('mcpResult', fn (): array => $this->response->toArray()['result'] ?? ['error' => $this->response->toArray()['error'] ?? null]);
    }

    /**
     * The tool's structured answer, as JSON gives it.
     *
     * @param  class-string<Tool>  $tool
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function runTool(string $tool, array $arguments = []): array
    {
        $result = CmsServer::tool($tool, $arguments)->mcpResult();
        $this->lastToolFailed = (bool) ($result['isError'] ?? isset($result['error']));
        $structured = $result['structuredContent'] ?? json_decode((string) ($result['content'][0]['text'] ?? 'null'), true);

        return json_decode((string) json_encode($structured), true) ?? ['_text' => $result['content'][0]['text'] ?? null, '_error' => $result['error'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function assertRefused(array $result, string ...$keys): void
    {
        $this->assertTrue($this->lastToolFailed, 'The tool should answer with an error.');
        $this->assertFalse($result['ok'] ?? null, 'The tool should answer ok: false.');

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $result['errors'], 'Errors: '.json_encode($result['errors']));
            $this->assertIsString($result['errors'][$key]);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function assertSaved(array $result): void
    {
        $this->assertFalse($this->lastToolFailed, 'The tool failed: '.json_encode($result));
        $this->assertTrue($result['ok'] ?? null, 'The tool should answer ok: true: '.json_encode($result));
        $this->assertSame([], $result['errors']);
    }

    /**
     * @param  array<string, true>  $lock
     * @return array<string, mixed>
     */
    protected function richSection(string $id, string $text, array $lock = []): array
    {
        return ['id' => $id, 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph($text)]], ...($lock === [] ? [] : ['lock' => $lock])];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    protected function makePage(string $path = '/', array $sections = [], bool $published = true, string $title = 'Utama'): SitePage
    {
        return SitePage::query()->create(['title' => $title, 'path' => $path, 'is_published' => $published, 'sections' => $sections]);
    }
}
