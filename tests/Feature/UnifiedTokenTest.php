<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Settings\SecuritySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Токен один: настройка security.access_token. Прокси, MCP и API проверяют
 * одно и то же значение, поэтому любой из трёх входов должен принимать его.
 */
class UnifiedTokenTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'unified-brain-token';

    protected function setUp(): void
    {
        parent::setUp();

        $security = app(SecuritySettings::class);
        $security->access_token = self::TOKEN;
        $security->save();
    }

    public function test_token_authorizes_the_proxy(): void
    {
        $this->withHeaders(['x-api-key' => self::TOKEN])
            ->getJson('/v1/models')
            ->assertOk();
    }

    public function test_token_authorizes_the_api(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])
            ->getJson('/api/v1/stats')
            ->assertOk();
    }

    public function test_token_authorizes_mcp(): void
    {
        $this->postJson('/mcp/knowledge', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.self::TOKEN,
            'Accept' => 'application/json, text/event-stream',
        ])->assertSuccessful();
    }

    public function test_token_in_the_address_works_for_the_proxy(): void
    {
        $this->getJson('/'.self::TOKEN.'/v1/models')->assertOk();
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not-a-token'])
            ->getJson('/v1/models')
            ->assertUnauthorized();

        $this->withHeaders(['Authorization' => 'Bearer not-a-token'])
            ->getJson('/api/v1/stats')
            ->assertUnauthorized();
    }

    public function test_empty_setting_falls_back_to_the_environment_token(): void
    {
        config(['brain.tokens.all' => 'env-token']);

        $security = app(SecuritySettings::class);
        $security->access_token = '';
        $security->save();

        $this->withHeaders(['Authorization' => 'Bearer env-token'])
            ->getJson('/api/v1/stats')
            ->assertOk();
    }
}
