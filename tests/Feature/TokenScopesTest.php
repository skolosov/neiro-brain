<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Settings\SecuritySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Один токен на все входы, но значение из репозитория (`dev-brain-token`)
 * пускает только localhost: иначе публичный сервер открывался бы по строке,
 * которую знает любой читатель исходников.
 */
class TokenScopesTest extends TestCase
{
    use RefreshDatabase;

    private const MCP_INITIALIZE = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [],
    ];

    public function test_generate_rotates_the_single_token(): void
    {
        $before = app(SecuritySettings::class)->refresh()->access_token;

        $this->artisan('brain:token --generate')->assertSuccessful();

        $after = app(SecuritySettings::class)->refresh()->access_token;

        $this->assertNotSame($before, $after);
        $this->assertStringStartsWith('nb-', $after);
    }

    public function test_generated_token_authorizes_every_channel(): void
    {
        $this->artisan('brain:token --generate')->assertSuccessful();

        $token = app(SecuritySettings::class)->refresh()->access_token;

        $this->withHeaders(['x-api-key' => $token])
            ->getJson('/v1/models')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json, text/event-stream',
        ])->postJson('/mcp/knowledge', self::MCP_INITIALIZE)->assertSuccessful();
    }

    public function test_shipped_default_token_is_rejected_from_a_public_address(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->withHeaders([
                'Authorization' => 'Bearer dev-brain-token',
                'Accept' => 'application/json, text/event-stream',
            ])
            ->postJson('/mcp/knowledge', self::MCP_INITIALIZE)
            ->assertUnauthorized();
    }

    public function test_shipped_default_token_is_rejected_for_the_proxy_from_a_public_address(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->withHeaders(['x-api-key' => 'dev-brain-token'])
            ->getJson('/v1/models')
            ->assertUnauthorized();
    }

    public function test_shipped_default_token_is_accepted_from_localhost(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'Authorization' => 'Bearer dev-brain-token',
                'Accept' => 'application/json, text/event-stream',
            ])
            ->postJson('/mcp/knowledge', self::MCP_INITIALIZE)
            ->assertSuccessful();
    }

    public function test_custom_token_is_accepted_from_a_public_address(): void
    {
        config(['brain.tokens.all' => 'public-token']);

        $security = app(SecuritySettings::class);
        $security->access_token = 'public-token';
        $security->save();

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->withHeaders([
                'Authorization' => 'Bearer public-token',
                'Accept' => 'application/json, text/event-stream',
            ])
            ->postJson('/mcp/knowledge', self::MCP_INITIALIZE)
            ->assertSuccessful();
    }

    public function test_prune_removes_the_legacy_token_columns(): void
    {
        $security = app(SecuritySettings::class);
        $security->access_token = 'nb-real-token';
        $security->save();

        $now = now();

        foreach ([['proxy', 'access_token'], ['security', 'mcp_token'], ['security', 'api_token']] as [$group, $name]) {
            DB::table('settings')->insert([
                'group' => $group,
                'name' => $name,
                'locked' => false,
                'payload' => json_encode('legacy', JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->artisan('brain:token --prune')->assertSuccessful();

        $this->assertSame(0, DB::table('settings')
            ->where(fn ($query) => $query
                ->where(fn ($inner) => $inner->where('group', 'proxy')->where('name', 'access_token'))
                ->orWhere(fn ($inner) => $inner->where('group', 'security')->whereIn('name', ['mcp_token', 'api_token'])))
            ->count());
    }

    public function test_prune_refuses_to_drop_the_last_real_token(): void
    {
        DB::table('settings')->insert([
            'group' => 'proxy',
            'name' => 'access_token',
            'locked' => false,
            'payload' => json_encode('legacy', JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('brain:token --prune')->assertFailed();

        $this->assertTrue(DB::table('settings')
            ->where('group', 'proxy')
            ->where('name', 'access_token')
            ->exists());
    }

    public function test_show_reports_where_the_token_comes_from(): void
    {
        $this->artisan('brain:token')
            ->expectsOutputToContain('настройки')
            ->assertSuccessful();
    }
}
