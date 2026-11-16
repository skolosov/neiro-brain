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

    public function test_config_url_comes_from_app_url(): void
    {
        // Публичный адрес задаётся один раз в APP_URL, а не выводится из того,
        // откуда открыли страницу: иначе у половины людей в конфиге окажется
        // 127.0.0.1, который у них не работает.
        $this->get('/')
            ->assertOk()
            ->assertSee(rtrim((string) config('app.url'), '/').'/mcp/knowledge', false);
    }
}
