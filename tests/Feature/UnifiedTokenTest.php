<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedTokenTest extends TestCase
{
    use RefreshDatabase;

    private const UNIFIED = 'unified-brain-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['brain.tokens.all' => self::UNIFIED]);
    }

    public function test_unified_token_authorizes_the_proxy(): void
    {
        $this->withHeaders(['x-api-key' => self::UNIFIED])
            ->getJson('/v1/models')
            ->assertOk();
    }

    public function test_unified_token_authorizes_the_api(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.self::UNIFIED])
            ->getJson('/api/v1/stats')
            ->assertOk();
    }

    public function test_unified_token_authorizes_mcp(): void
    {
        $this->postJson('/mcp/knowledge', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.self::UNIFIED,
            'Accept' => 'application/json, text/event-stream',
        ])->assertSuccessful();
    }

    public function test_per_scope_tokens_keep_working_alongside_the_unified_one(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer dev-proxy-token'])
            ->getJson('/v1/models')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer dev-api-token'])
            ->getJson('/api/v1/stats')
            ->assertOk();
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
}
