<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class McpRouteTest extends TestCase
{
    private const TOKEN = 'mcp-test-token-1234567890';

    /**
     * @param  array<string, string>  $headers
     */
    private function ping(array $headers = []): TestResponse
    {
        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], $headers);
    }

    private function bearer(string $token = self::TOKEN): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_without_a_configured_token_the_address_does_not_exist(): void
    {
        config(['filament-cms.mcp.token' => null]);

        $this->ping($this->bearer())->assertNotFound();
        $this->ping(['Authorization' => 'Bearer '])->assertNotFound();
    }

    public function test_a_missing_token_is_refused(): void
    {
        config(['filament-cms.mcp.token' => self::TOKEN]);

        $this->ping()->assertUnauthorized();
    }

    public function test_a_wrong_token_is_refused_and_the_real_one_is_never_shown(): void
    {
        config(['filament-cms.mcp.token' => self::TOKEN]);

        $response = $this->ping($this->bearer('mcp-test-token-wrong'))->assertUnauthorized();

        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
    }

    public function test_the_right_token_reaches_the_server(): void
    {
        config(['filament-cms.mcp.token' => self::TOKEN]);

        $this->ping($this->bearer())->assertOk()->assertJsonPath('id', 1)->assertJsonPath('jsonrpc', '2.0');
    }

    public function test_a_client_can_start_a_session_and_list_the_tools(): void
    {
        config(['filament-cms.mcp.token' => self::TOKEN]);

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
            'protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1'],
        ]], $this->bearer())->assertOk()->assertJsonPath('result.serverInfo.name', 'Filament CMS');

        $tools = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $this->bearer())
            ->assertOk()
            ->json('result.tools');

        $this->assertContains('site_overview', array_column($tools, 'name'));
    }

    public function test_sixty_calls_a_minute_are_allowed_and_the_next_one_waits(): void
    {
        config(['filament-cms.mcp.token' => self::TOKEN]);

        foreach (range(1, 60) as $call) {
            $this->ping($this->bearer())->assertOk();
        }

        $this->ping($this->bearer())->assertTooManyRequests();
    }

    public function test_the_mcp_address_takes_no_csrf_token(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/mcp', 'POST'));
        $middleware = app('router')->gatherRouteMiddleware($route);

        foreach ($middleware as $name) {
            $this->assertStringNotContainsStringIgnoringCase('csrf', is_string($name) ? $name : '');
            $this->assertStringNotContainsString('PreventRequestForgery', is_string($name) ? $name : '');
        }
    }

    public function test_the_page_route_never_takes_the_mcp_address(): void
    {
        config(['filament-cms.mcp.token' => self::TOKEN]);
        SitePage::query()->create(['title' => 'MCP', 'path' => '/mcp', 'is_published' => true, 'sections' => []]);

        $pageRoute = app('router')->getRoutes()->getByName('filament-cms.page');

        $this->assertFalse($pageRoute->matches(Request::create('/mcp')));
        $this->assertFalse($pageRoute->matches(Request::create('/mcp/anything')));
        $this->assertTrue($pageRoute->matches(Request::create('/mcp-promo')));
        $this->get('/mcp', $this->bearer())->assertStatus(405);
    }
}
