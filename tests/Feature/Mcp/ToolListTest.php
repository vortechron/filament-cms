<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Laravel\Mcp\Server\Testing\TestListResponse;
use Vortechron\FilamentCms\Mcp\CmsServer;

class ToolListTest extends McpTestCase
{
    /**
     * The tools of the contract (v2-plan.md section 11, C4).
     */
    private const TOOLS = [
        'site_overview', 'page_format', 'list_documents', 'get_document', 'create_document', 'save_document',
        'delete_document', 'update_section', 'insert_section', 'move_section', 'remove_section', 'get_kit',
        'save_kit', 'get_settings', 'save_settings', 'list_entries', 'save_entry', 'delete_entry',
        'list_form_submissions', 'update_form_submission', 'list_versions', 'restore_version', 'render_document',
    ];

    public function test_the_server_offers_every_tool_of_the_contract_with_words_an_agent_can_follow(): void
    {
        TestListResponse::macro('items', fn (): array => $this->items);

        $tools = array_column(CmsServer::tools()->items(), null, 'name');

        $this->assertEqualsCanonicalizing(self::TOOLS, array_keys($tools));

        foreach ($tools as $name => $tool) {
            $this->assertGreaterThan(40, strlen($tool['description']), $name);
            $this->assertSame('object', $tool['inputSchema']['type'], $name);

            foreach ((array) $tool['inputSchema']['properties'] as $property => $schema) {
                $this->assertNotEmpty($schema['description'] ?? null, "{$name}.{$property} has no description");
            }
        }
    }

    public function test_the_instructions_say_what_the_site_is_and_that_writes_are_checked_like_the_panel(): void
    {
        config(['app.name' => 'Kedai Ujian']);

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']], ['Authorization' => 'Bearer '.$this->token()])
            ->assertOk()
            ->assertJsonPath('result.instructions', fn (string $text): bool => str_contains($text, 'Kedai Ujian') && str_contains($text, 'same checks') && str_contains($text, '{key} short codes'));
    }

    private function token(): string
    {
        config(['filament-cms.mcp.token' => 'mcp-test-token-1234567890']);

        return 'mcp-test-token-1234567890';
    }
}
