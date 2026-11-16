<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Enums\PanelRole;
use App\Models\User;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Support\OwnerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

/**
 * OAuth-подключение MCP: в конфиге клиента только адрес.
 *
 * Клиент получает 401 с указанием, где авторизоваться, читает метаданные,
 * регистрируется, открывает браузер, человек подтверждает доступ в панели и
 * дальше клиент ходит с Bearer-токеном Passport. Личный токен `nb-…` остаётся
 * вторым, прежним путём — он не сломан.
 */
class McpOAuthTest extends TestCase
{
    use RefreshDatabase;

    private const MCP_INITIALIZE = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [],
    ];

    private const REDIRECT_URI = 'http://127.0.0.1:41234/oauth/callback';

    private const STATE = 'state-42';

    /** Вход в панель, привязанный к человеку `users`. Один на человека. */
    private function panelAccount(User $user): MoonshineUser
    {
        $account = MoonshineUser::query()->where('user_id', $user->getKey())->first();

        if ($account !== null) {
            return $account;
        }

        $account = MoonshineUser::query()->create([
            'name' => $user->name,
            'email' => $user->email,
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => PanelRole::User->value,
        ]);

        $account->forceFill(['user_id' => $user->getKey()])->save();

        return $account;
    }

    private function secondOwner(): User
    {
        return User::query()->create([
            'name' => 'Второй владелец',
            'email' => 'second@neiro-brain.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    /** Публичный клиент через динамическую регистрацию, как это делает DSH. */
    private function registerClient(string $name = 'DSH тест'): string
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => $name,
            'redirect_uris' => [self::REDIRECT_URI],
        ])->assertCreated();

        $this->assertSame('none', $response->json('token_endpoint_auth_method'));
        $this->assertIsString($response->json('client_id'));

        return (string) $response->json('client_id');
    }

    /**
     * PKCE: S256-челлендж и его verifier.
     *
     * @return array{verifier: string, challenge: string}
     */
    private function pkce(): array
    {
        $verifier = Str::random(64);

        return [
            'verifier' => $verifier,
            'challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        ];
    }

    private function authorizeUrl(string $clientId, string $challenge): string
    {
        return '/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'scope' => 'mcp:use',
            'state' => self::STATE,
        ]);
    }

    /**
     * Полный путь клиента: регистрация, согласие человека, обмен кода на токен.
     *
     * @return array{access_token: string, refresh_token: string, client_id: string}
     */
    private function connect(User $user): array
    {
        $clientId = $this->registerClient();
        $pkce = $this->pkce();
        $account = $this->panelAccount($user);

        $consent = $this->actingAs($account, 'moonshine')
            ->get($this->authorizeUrl($clientId, $pkce['challenge']))
            ->assertOk();

        $consent->assertSee('DSH тест')
            ->assertSee('Разрешить')
            ->assertSee('Отказать');

        // Страница согласия кладёт запрос авторизации в сессию; забираем его,
        // чтобы подтверждение не зависело от переноса cookie между запросами.
        $approved = $this->withSession([
            'authToken' => session('authToken'),
            'authRequest' => session('authRequest'),
        ])->actingAs($account, 'moonshine')
            ->post('/oauth/authorize', [
                'client_id' => $clientId,
                'auth_token' => session('authToken'),
            ]);

        $approved->assertRedirect();
        $location = (string) $approved->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT_URI.'?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame(self::STATE, $query['state'] ?? null);
        $this->assertNotEmpty($query['code'] ?? null);

        $token = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $query['code'],
            'code_verifier' => $pkce['verifier'],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame('Bearer', $token->json('token_type'));
        $this->assertNotEmpty($token->json('access_token'));

        return [
            'access_token' => (string) $token->json('access_token'),
            'refresh_token' => (string) $token->json('refresh_token'),
            'client_id' => $clientId,
        ];
    }

    /**
     * MCP-вызов OAuth-токеном.
     *
     * @param  array<string, mixed>  $payload
     */
    private function mcp(array $payload, string $token): TestResponse
    {
        return $this->postJson('/mcp/knowledge', $payload, $this->mcpHeaders($token));
    }

    public function test_oauth_metadata_is_published(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource')
            ->assertOk()
            ->assertJsonPath('resource', url('/'))
            ->assertJsonPath('scopes_supported', ['mcp:use']);

        $this->getJson('/.well-known/oauth-protected-resource/mcp/knowledge')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp/knowledge'))
            ->assertJsonPath('scopes_supported', ['mcp:use'])
            ->assertJsonPath('authorization_servers', [url('/')]);

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
            ->assertJsonPath('token_endpoint', route('passport.token'))
            ->assertJsonPath('registration_endpoint', route('mcp.oauth.register'))
            ->assertJsonPath('scopes_supported', ['mcp:use'])
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('response_types_supported', ['code']);
    }

    public function test_401_points_to_the_resource_metadata(): void
    {
        $response = $this->postJson('/mcp/knowledge', self::MCP_INITIALIZE, [
            'Accept' => 'application/json, text/event-stream',
        ])->assertUnauthorized();

        $header = (string) $response->headers->get('WWW-Authenticate');

        $this->assertStringContainsString('resource_metadata="', $header);
        $this->assertStringContainsString(
            route('mcp.oauth.protected-resource.nested', ['path' => 'mcp/knowledge']),
            $header,
        );
    }

    public function test_dynamic_client_registration_returns_a_public_client(): void
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => 'DSH тест',
            'redirect_uris' => [self::REDIRECT_URI],
        ])->assertCreated();

        $this->assertNotEmpty($response->json('client_id'));
        $this->assertSame('none', $response->json('token_endpoint_auth_method'));
        $this->assertContains('authorization_code', $response->json('grant_types'));

        // Публичный клиент: секрета нет, значит утечь ему неоткуда.
        $this->assertNull($response->json('client_secret'));
    }

    public function test_authorization_without_session_redirects_to_the_panel_login(): void
    {
        $clientId = $this->registerClient();

        $this->get($this->authorizeUrl($clientId, $this->pkce()['challenge']))
            ->assertRedirect('/admin/login');

        // Адрес согласия запоминается: после входа MoonShine вернёт человека сюда.
        $this->assertStringContainsString('/oauth/authorize', (string) session('url.intended'));
    }

    public function test_panel_account_without_a_linked_person_cannot_consent(): void
    {
        $clientId = $this->registerClient();

        $serviceAccount = MoonshineUser::query()->create([
            'name' => 'Служебная учётка',
            'email' => 'service@panel.test',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => PanelRole::Admin->value,
        ]);

        // За входом нет человека `users`: согласие выдавать некому, и редирект
        // на вход был бы петлёй.
        $this->actingAs($serviceAccount, 'moonshine')
            ->get($this->authorizeUrl($clientId, $this->pkce()['challenge']))
            ->assertForbidden()
            ->assertSee('не привязан к человеку');
    }

    public function test_consent_page_shows_the_client_and_approves_with_a_code(): void
    {
        $user = $this->owner();
        $account = $this->panelAccount($user);
        $clientId = $this->registerClient('DSH тест');

        $consent = $this->actingAs($account, 'moonshine')
            ->get($this->authorizeUrl($clientId, $this->pkce()['challenge']));

        $consent->assertOk()
            ->assertSee('DSH тест')
            ->assertSee('Доступ к вашей базе знаний')
            ->assertSee('Разрешить')
            ->assertSee('Отказать')
            ->assertSee($user->email);

        $this->withSession([
            'authToken' => session('authToken'),
            'authRequest' => session('authRequest'),
        ])->actingAs($account, 'moonshine')
            ->post('/oauth/authorize', [
                'client_id' => $clientId,
                'auth_token' => session('authToken'),
            ])
            ->assertRedirect()
            ->assertRedirectContains('code=');
    }

    public function test_oauth_token_opens_mcp_and_sees_only_its_owners_cards(): void
    {
        $user = $this->owner();
        $other = $this->secondOwner();

        app(OwnerContext::class)->set($user);
        $own = app(KnowledgeWriter::class)->write(new ExtractedEntry(
            kind: KnowledgeKind::Fact,
            title: 'Своя карточка OAuth',
            content: 'Личная карточка владельца OAuth-токена.',
        ))['entry'];

        app(OwnerContext::class)->set($other);
        $foreign = app(KnowledgeWriter::class)->write(new ExtractedEntry(
            kind: KnowledgeKind::Fact,
            title: 'Чужая карточка OAuth',
            content: 'Секрет второго владельца, который не должен утечь.',
        ))['entry'];

        app(OwnerContext::class)->set($user);

        $token = $this->connect($user)['access_token'];

        $this->mcp(self::MCP_INITIALIZE, $token)->assertSuccessful();

        $search = $this->mcp([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'search_knowledge',
                'arguments' => ['query' => 'карточка OAuth'],
            ],
        ], $token)->assertOk();

        $content = (string) $search->getContent();
        $this->assertStringContainsString($own->title, $content);
        $this->assertStringNotContainsString($foreign->title, $content);

        // Чужой id не открывается и по прямой ссылке.
        $this->mcp([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_knowledge',
                'arguments' => ['id' => $foreign->id],
            ],
        ], $token)
            ->assertOk()
            ->assertSee('не найдена');
    }

    public function test_panel_shows_connected_apps_and_revoke_stops_the_token(): void
    {
        $user = $this->owner();
        $account = $this->panelAccount($user);
        $token = $this->connect($user)['access_token'];

        $this->mcp(self::MCP_INITIALIZE, $token)->assertSuccessful();

        $indexUrl = route('moonshine.resource.page', [
            'resourceUri' => 'api-token-resource',
            'pageUri' => 'api-token-index-page',
        ]);

        $this->actingAs($account, 'moonshine')
            ->get($indexUrl)
            ->assertOk()
            ->assertSee('Подключённые приложения')
            ->assertSee('DSH тест')
            ->assertSee('Отозвать');

        $passportToken = Passport::token()->newQuery()
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $this->actingAs($account, 'moonshine')
            ->delete(route('brain.admin.oauth-tokens.destroy', ['token' => $passportToken->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('oauth_access_tokens', ['id' => $passportToken->id]);

        $this->mcp(self::MCP_INITIALIZE, $token)->assertUnauthorized();
    }

    public function test_foreign_connected_app_is_not_revoked(): void
    {
        $user = $this->owner();
        $other = $this->secondOwner();

        $this->connect($other);

        $foreign = Passport::token()->newQuery()
            ->where('user_id', $other->getKey())
            ->firstOrFail();

        $this->actingAs($this->panelAccount($user), 'moonshine')
            ->delete(route('brain.admin.oauth-tokens.destroy', ['token' => $foreign->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $foreign->id]);
    }

    public function test_token_without_the_mcp_scope_is_forbidden(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Тест', 'users');

        // Passport выдаёт токен без области mcp:use: подписи и срока хватает,
        // а права на базу знаний нет.
        $plain = $this->owner()->createToken('Без области', [])->accessToken;

        $this->mcp(self::MCP_INITIALIZE, $plain)->assertForbidden();
    }

    public function test_oauth_token_of_a_disabled_user_is_rejected(): void
    {
        $user = $this->owner();
        $token = $this->connect($user)['access_token'];

        $user->forceFill(['is_active' => false])->save();

        $this->mcp(self::MCP_INITIALIZE, $token)->assertUnauthorized();
    }

    public function test_personal_token_still_opens_mcp(): void
    {
        $issued = $this->issueToken($this->owner(), ['read', 'write']);

        $this->mcp(self::MCP_INITIALIZE, $issued['plain'])->assertSuccessful();
    }
}
