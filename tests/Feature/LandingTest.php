<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Лендинг — витрина подключения: он должен отдавать копируемый конфиг MCP
 * с адресом от APP_URL, а не установочные скрипты вместо него.
 */
class LandingTest extends TestCase
{
    public function test_landing_shows_copyable_mcp_config(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('mcpServers', false);
        $response->assertSee(url('/mcp/knowledge'), false);
        $response->assertSee('nb-copy-mcp', false);
        $response->assertSee('Bearer nb-', false);
    }

    public function test_config_url_is_built_not_hardcoded(): void
    {
        // В тестах APP_URL = http://neiro-brain.local, поэтому захардкоженный
        // localhost сразу выдаст себя: в конфиге должен стоять адрес от App URL.
        $this->get('/')
            ->assertOk()
            ->assertSee(url('/mcp/knowledge'), false)
            ->assertDontSee('127.0.0.1/mcp/knowledge', false);
    }
}
