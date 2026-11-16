<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Enums\KnowledgeScope;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Support\OwnerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Кабинет: вход по web-гарду, только свои токены и одноразовый показ плейна.
 */
class CabinetTest extends TestCase
{
    use RefreshDatabase;

    private const MCP_INITIALIZE = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [],
    ];

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function user(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Пользователь',
            'email' => 'user@example.test',
            'password' => 'password',
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/app')->assertRedirect('/login');
        $this->get('/app/tokens')->assertRedirect('/login');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Вход в кабинет');
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = $this->user();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/app');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_does_not_log_in(): void
    {
        $user = $this->user();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_disabled_user_cannot_log_in(): void
    {
        $user = $this->user(['is_active' => false]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_dashboard_shows_only_own_tokens(): void
    {
        $user = $this->user();
        $this->issueToken($user);

        $other = $this->user(['email' => 'other@example.test']);
        app(TokenIssuer::class)->issue($other, 'Чужой токен', ['read', 'write']);

        $this->actingAs($user)
            ->get('/app')
            ->assertOk()
            ->assertSee('Здравствуйте')
            ->assertSee('Тестовый токен')
            ->assertDontSee('Чужой токен');

        $this->actingAs($user)
            ->get('/app/tokens')
            ->assertOk()
            ->assertSee('Тестовый токен')
            ->assertDontSee('Чужой токен');
    }

    public function test_issued_token_is_shown_once_and_opens_mcp_and_api(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/app/tokens', [
                'name' => 'Ноутбук',
                'abilities' => ['read', 'write'],
            ])
            ->assertRedirect(route('app.tokens.index'));

        $response = $this->actingAs($user)->get('/app/tokens')->assertOk();
        $response->assertSee('Ноутбук')->assertSee('второй раз мы его не покажем');

        preg_match('/nb-[A-Za-z0-9]{40}/', (string) $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'Плейн-токен не показан после выпуска.');

        $plain = $matches[0];

        // Второй раз значение не показывается.
        $this->actingAs($user)
            ->get('/app/tokens')
            ->assertOk()
            ->assertDontSee($plain);

        // Токен, выпущенный в кабинете, пускает MCP и API.
        $this->postJson('/mcp/knowledge', self::MCP_INITIALIZE, $this->mcpHeaders($plain))
            ->assertSuccessful();

        $this->getJson('/api/v1/search?q=laravel', $this->mcpHeaders($plain))
            ->assertOk();
    }

    public function test_user_can_revoke_own_token(): void
    {
        $user = $this->user();
        $issued = $this->issueToken($user);

        $this->actingAs($user)
            ->delete('/app/tokens/'.$issued['token']->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('api_tokens', ['id' => $issued['token']->id]);

        $this->withToken($issued['plain'])
            ->getJson('/api/v1/stats')
            ->assertUnauthorized();
    }

    public function test_foreign_token_cannot_be_revoked(): void
    {
        $user = $this->user();
        $other = $this->user(['email' => 'other@example.test']);
        $foreign = $this->issueToken($other);

        $this->actingAs($user)
            ->delete('/app/tokens/'.$foreign['token']->id)
            ->assertNotFound();

        $this->assertDatabaseHas('api_tokens', ['id' => $foreign['token']->id]);
    }

    public function test_profile_can_be_updated(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->patch('/app/profile', [
                'name' => 'Новое имя',
                'locale' => 'en',
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Новое имя', $user->name);
        $this->assertSame('en', $user->locale);
    }

    public function test_cabinet_search_finds_only_own_cards(): void
    {
        $user = $this->user();
        $other = $this->user(['email' => 'other@example.test']);

        $mine = $this->writeFor($user, 'Мой Laravel стек', 'Laravel 13 и PostgreSQL.');
        $this->writeFor($other, 'Чужой Laravel стек', 'Laravel у другого человека.');

        $this->actingAs($user)
            ->get('/app/search?q=Laravel')
            ->assertOk()
            ->assertSee($mine->title)
            ->assertDontSee('Чужой Laravel стек');
    }

    public function test_foreign_entry_is_not_found(): void
    {
        $user = $this->user();
        $other = $this->user(['email' => 'other@example.test']);
        $foreign = $this->writeFor($other, 'Чужая карточка', 'Секрет другого человека.');

        $this->actingAs($user)
            ->get('/app/entries/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_cabinet_graph_contains_only_own_nodes(): void
    {
        $user = $this->user();
        $other = $this->user(['email' => 'other@example.test']);

        $this->writeFor($user, 'Своя карточка для графа', 'Мой факт про Laravel.', 'neiro-brain');
        $this->writeFor($other, 'Чужая карточка для графа', 'Чужой факт про Laravel.', 'neiro-brain');

        $this->actingAs($user)
            ->get('/app/graph')
            ->assertOk()
            ->assertSee('Своя карточка для графа')
            ->assertDontSee('Чужая карточка для графа');
    }

    public function test_entry_history_shows_the_superseded_version(): void
    {
        $user = $this->user();

        app(OwnerContext::class)->set($user);

        $old = $this->writeFor($user, 'Старая версия карточки', 'Старый текст про Laravel.');
        $new = app(KnowledgeWriter::class)->supersede($old, new ExtractedEntry(
            kind: KnowledgeKind::Stack,
            title: 'Новая версия карточки',
            content: 'Новый текст про Laravel 13.',
            project: 'neiro-brain',
            scope: KnowledgeScope::Project,
        ));

        $this->actingAs($user)
            ->get('/app/entries/'.$new->id)
            ->assertOk()
            ->assertSee('Новая версия карточки')
            ->assertSee('Старая версия карточки')
            ->assertSee('устаревшая версия');
    }

    /**
     * Записать карточку от имени конкретного владельца.
     */
    private function writeFor(
        User $owner,
        string $title,
        string $content,
        ?string $project = null,
    ): KnowledgeEntry {
        app(OwnerContext::class)->set($owner);

        $result = app(KnowledgeWriter::class)->write(new ExtractedEntry(
            kind: KnowledgeKind::Stack,
            title: $title,
            content: $content,
            project: $project,
            scope: $project !== null ? KnowledgeScope::Project : KnowledgeScope::Profile,
        ));

        $this->assertIsArray($result);

        return $result['entry'];
    }
}
