<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Лендинг — витрина подключения. После перехода на MCP OAuth в конфиге не должно
 * быть токена: только адрес, а вход происходит в браузере.
 */
class LandingTest extends TestCase
{
    public function test_landing_shows_copyable_mcp_config_without_a_token(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('mcpServers', false);
        $response->assertSee(url('/mcp/knowledge'), false);
        $response->assertSee('nb-copy-mcp', false);
        // Токена в конфиге быть не должно — иначе на экране снова учат его вставлять.
        $response->assertDontSee('"Authorization"', false);
        $response->assertDontSee('Bearer nb-', false);
    }

    public function test_landing_explains_the_oauth_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Разрешить')
            ->assertSee('Подключённые приложения');
    }

    public function test_config_url_is_built_not_hardcoded(): void
    {
        // url() берёт адрес из запроса, поэтому захардкоженный localhost сразу
        // выдаст себя: страница обязана показывать тот хост, с которого открыта.
        $this->get('/')
            ->assertOk()
            ->assertSee(url('/mcp/knowledge'), false)
            ->assertDontSee('127.0.0.1/mcp/knowledge', false);
    }
}
