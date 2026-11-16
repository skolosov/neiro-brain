<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Личный токен — единственный вход в MCP и API.
 *
 * Проверяем обе способности, отзыв, срок действия, дев-токен только для
 * localhost и то, что чужой токен не открывает чужие карточки.
 */
class TokenAuthTest extends TestCase
{
    use RefreshDatabase;

    private const MCP_INITIALIZE = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [],
    ];

    private function mcp(string $token, string $ip = '127.0.0.1'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/mcp/knowledge', self::MCP_INITIALIZE, $this->mcpHeaders($token));
    }

    private function secondOwner(): User
    {
        return User::query()->create([
            'name' => 'Второй владелец',
            'email' => 'second@neiro-brain.test',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_personal_token_authorizes_mcp(): void
    {
        $issued = $this->issueToken($this->owner(), ['read', 'write']);

        $this->mcp($issued['plain'])->assertSuccessful();
    }

    public function test_personal_token_authorizes_api_read_and_write(): void
    {
        $issued = $this->issueToken($this->owner(), ['read', 'write']);

        $this->withToken($issued['plain'])
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->withToken($issued['plain'])
            ->postJson('/api/v1/entries', [
                'title' => 'Личная карточка',
                'content' => 'Карточка записана личным токеном через API.',
            ])
            ->assertCreated();
    }

    public function test_request_without_token_is_rejected(): void
    {
        $this->getJson('/api/v1/stats')->assertUnauthorized();

        $this->postJson('/mcp/knowledge', self::MCP_INITIALIZE, [
            'Accept' => 'application/json, text/event-stream',
        ])->assertUnauthorized();
    }

    public function test_x_api_key_header_is_accepted(): void
    {
        $issued = $this->issueToken($this->owner());

        $this->withHeaders(['x-api-key' => $issued['plain']])
            ->getJson('/api/v1/stats')
            ->assertOk();
    }

    public function test_revoked_token_is_rejected(): void
    {
        $issued = $this->issueToken($this->owner());

        $issued['token']->delete();

        $this->withToken($issued['plain'])
            ->getJson('/api/v1/stats')
            ->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $issued = $this->issueToken($this->owner(), ['read', 'write'], now()->subMinute());

        $this->withToken($issued['plain'])
            ->getJson('/api/v1/stats')
            ->assertUnauthorized();

        $this->mcp($issued['plain'])->assertUnauthorized();
    }

    public function test_read_only_token_cannot_write(): void
    {
        $issued = $this->issueToken($this->owner(), ['read']);

        $this->withToken($issued['plain'])
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->withToken($issued['plain'])
            ->postJson('/api/v1/entries', [
                'title' => 'Писать нельзя',
                'content' => 'У токена нет права write.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('knowledge_entries', 0);
    }

    public function test_write_implies_read(): void
    {
        $issued = $this->issueToken($this->owner(), ['write']);

        $this->withToken($issued['plain'])
            ->getJson('/api/v1/stats')
            ->assertOk();
    }

    public function test_dev_token_works_from_localhost_in_local_environment(): void
    {
        $this->app['env'] = 'local';

        $this->mcp((string) config('brain.tokens.dev'), '127.0.0.1')->assertSuccessful();
    }

    public function test_dev_token_is_rejected_from_a_public_address(): void
    {
        $this->app['env'] = 'local';

        $this->mcp((string) config('brain.tokens.dev'), '8.8.8.8')->assertUnauthorized();
    }

    public function test_dev_token_does_not_work_outside_local_environment(): void
    {
        $this->mcp((string) config('brain.tokens.dev'), '127.0.0.1')->assertUnauthorized();
    }

    public function test_dev_token_without_an_active_owner_returns_503(): void
    {
        $this->app['env'] = 'local';

        User::query()->delete();

        $this->mcp((string) config('brain.tokens.dev'), '127.0.0.1')->assertStatus(503);
    }

    public function test_token_of_a_disabled_user_is_rejected(): void
    {
        $owner = $this->owner();
        $issued = $this->issueToken($owner);

        $owner->forceFill(['is_active' => false])->save();

        $this->withToken($issued['plain'])
            ->getJson('/api/v1/stats')
            ->assertUnauthorized();
    }

    public function test_foreign_token_cannot_read_another_owners_card(): void
    {
        $first = $this->owner();
        $this->withOwner($first);

        $card = app(KnowledgeWriter::class)->write(
            new ExtractedEntry(
                kind: KnowledgeKind::Fact,
                title: 'Секрет первого',
                content: 'Эта карточка принадлежит только первому владельцу.',
            ),
        )['entry'];

        $issued = $this->issueToken($this->secondOwner());

        $this->withToken($issued['plain'])
            ->getJson('/api/v1/entries/'.$card->id)
            ->assertNotFound();

        $this->withToken($issued['plain'])
            ->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonPath('data.entries', 0);
    }

    public function test_last_used_at_is_throttled_to_once_a_minute(): void
    {
        $issued = $this->issueToken($this->owner());
        $token = $issued['token'];

        $this->withToken($issued['plain'])->getJson('/api/v1/stats')->assertOk();

        $token->refresh();
        $this->assertNotNull($token->last_used_at);

        // Свежая отметка не перезаписывается: иначе каждый MCP-запрос писал бы в базу.
        $recent = now()->startOfSecond()->subSeconds(30);
        $token->forceFill(['last_used_at' => $recent])->saveQuietly();

        $this->withToken($issued['plain'])->getJson('/api/v1/stats')->assertOk();

        $this->assertTrue($token->refresh()->last_used_at->equalTo($recent));

        // Просроченная отметка обновляется при следующем запросе.
        $old = now()->startOfSecond()->subMinutes(5);
        $token->forceFill(['last_used_at' => $old])->saveQuietly();

        $this->withToken($issued['plain'])->getJson('/api/v1/stats')->assertOk();

        $this->assertTrue($token->refresh()->last_used_at->gt($old));
    }

    public function test_only_the_hash_is_stored(): void
    {
        $before = ApiToken::query()->count();
        $issued = $this->issueToken($this->owner());

        $this->assertStringStartsWith('nb-', $issued['plain']);
        $this->assertSame(43, mb_strlen($issued['plain']));
        $this->assertSame(TokenIssuer::hash($issued['plain']), $issued['token']->token_hash);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $issued['plain']]);
        $this->assertSame($before + 1, ApiToken::query()->count());
    }
}
