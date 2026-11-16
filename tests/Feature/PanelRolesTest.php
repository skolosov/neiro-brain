<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Enums\KnowledgeScope;
use App\Enums\PanelRole;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Support\OwnerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

/**
 * Интерфейс один — админка MoonShine, доступ разграничен ролью.
 *
 * Обычный человек видит только свои карточки и свои токены, чужие ресурсы и
 * настройки ему закрыты, а администратор видит всё. Чужой id карточки не
 * открывается ни формой, ни страницей просмотра.
 */
class PanelRolesTest extends TestCase
{
    use RefreshDatabase;

    private const MCP_INITIALIZE = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [],
    ];

    private function user(string $email, string $name = 'Человек'): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'locale' => 'ru',
            'is_active' => true,
        ]);
    }

    /**
     * Вход в панель. `user_id` не входит в fillable, поэтому привязка к человеку
     * ставится отдельно — как это делает brain:user.
     */
    private function panelAccount(User $user, PanelRole $role = PanelRole::User): MoonshineUser
    {
        $account = MoonshineUser::query()->create([
            'name' => $user->name,
            'email' => $user->email,
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $role->value,
        ]);

        $account->forceFill(['user_id' => $user->getKey()])->save();

        return $account;
    }

    private function admin(): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'name' => 'Администратор',
            'email' => 'admin@panel.test',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => PanelRole::Admin->value,
        ]);
    }

    private function entryFor(User $owner, string $title): KnowledgeEntry
    {
        app(OwnerContext::class)->set($owner);

        return app(KnowledgeWriter::class)->write(new ExtractedEntry(
            kind: KnowledgeKind::Stack,
            title: $title,
            content: 'Laravel 13, PostgreSQL, MoonShine.',
            project: 'neiro-brain',
            scope: KnowledgeScope::Project,
            importance: 4,
            confidence: 1.0,
        ))['entry'];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function knowledgeIndexUrl(array $query = []): string
    {
        return route('moonshine.resource.page', [
            'resourceUri' => 'knowledge-entry-resource',
            'pageUri' => 'knowledge-entry-index-page',
            ...$query,
        ]);
    }

    private function tokenIndexUrl(): string
    {
        return route('moonshine.resource.page', [
            'resourceUri' => 'api-token-resource',
            'pageUri' => 'api-token-index-page',
        ]);
    }

    /** (а) В списке знаний обычный человек видит только свои карточки. */
    public function test_a_regular_user_sees_only_own_cards_in_the_list(): void
    {
        $mine = $this->user('mine@panel.test');
        $other = $this->user('other@panel.test', 'Другой человек');

        $ownCard = $this->entryFor($mine, 'Моя карточка');
        $foreignCard = $this->entryFor($other, 'Чужая карточка');

        app(OwnerContext::class)->forget();

        $account = $this->panelAccount($mine);

        $this->actingAs($account, 'moonshine')
            ->get($this->knowledgeIndexUrl())
            ->assertOk()
            ->assertSee($ownCard->title)
            ->assertDontSee($foreignCard->title);

        // Страница «Знания» с карточками (поиск/обзор) показывает те же свои строки.
        $this->actingAs($account, 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'search-page']))
            ->assertOk()
            ->assertSee($ownCard->title)
            ->assertDontSee($foreignCard->title);
    }

    /** (б) Чужой id карточки не открывается ни detail, ни формой, ни поиском. */
    public function test_b_foreign_card_id_is_not_found(): void
    {
        $mine = $this->user('mine@panel.test');
        $other = $this->user('other@panel.test', 'Другой человек');

        $ownCard = $this->entryFor($mine, 'Моя карточка');
        $foreignCard = $this->entryFor($other, 'Чужая карточка');

        app(OwnerContext::class)->forget();

        $account = $this->panelAccount($mine);

        $detailUrl = route('moonshine.resource.page', [
            'resourceUri' => 'knowledge-entry-resource',
            'pageUri' => 'knowledge-entry-detail-page',
            'resourceItem' => $foreignCard->id,
        ]);

        $this->actingAs($account, 'moonshine')->get($detailUrl)->assertNotFound();

        $formUrl = route('moonshine.resource.page', [
            'resourceUri' => 'knowledge-entry-resource',
            'pageUri' => 'knowledge-entry-form-page',
            'resourceItem' => $foreignCard->id,
        ]);

        $this->actingAs($account, 'moonshine')->get($formUrl)->assertNotFound();

        $this->actingAs($account, 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'search-page', 'entry' => $foreignCard->id]))
            ->assertNotFound();

        // Своя карточка открывается — значит, закрыт именно чужой id.
        $ownDetailUrl = route('moonshine.resource.page', [
            'resourceUri' => 'knowledge-entry-resource',
            'pageUri' => 'knowledge-entry-detail-page',
            'resourceItem' => $ownCard->id,
        ]);

        $this->actingAs($account, 'moonshine')
            ->get($ownDetailUrl)
            ->assertOk()
            ->assertSee($ownCard->title);
    }

    /** (в) Обычный человек не видит «Люди», «Администраторы», «Роли», «Настройки». */
    public function test_v_regular_user_does_not_see_admin_menu_items(): void
    {
        $mine = $this->user('menu@panel.test');
        $account = $this->panelAccount($mine);

        $this->actingAs($account, 'moonshine')
            ->get(route('moonshine.index'))
            ->assertOk()
            ->assertSee('Поиск')
            ->assertSee('Токены')
            ->assertDontSee('Люди')
            ->assertDontSee('Настройки')
            // Прямые ссылки на «Люди», «Администраторы» и «Роли» в меню не попадают.
            ->assertDontSee('user-resource', false)
            ->assertDontSee('role-resource', false);
    }

    /** (в) Администратор видит меню целиком. */
    public function test_v_admin_sees_the_full_menu(): void
    {
        $this->actingAs($this->admin(), 'moonshine')
            ->get(route('moonshine.index'))
            ->assertOk()
            ->assertSee('Люди')
            ->assertSee('Настройки')
            ->assertSee('Токены')
            ->assertSee('user-resource', false);
    }

    /** Прямые адреса админских разделов обычному закрыты. */
    public function test_v_admin_only_resources_are_forbidden_for_a_regular_user(): void
    {
        $mine = $this->user('forbidden@panel.test');
        $account = $this->panelAccount($mine);

        $this->actingAs($account, 'moonshine')
            ->get(route('moonshine.resource.page', [
                'resourceUri' => 'user-resource',
                'pageUri' => 'user-index-page',
            ]))
            ->assertForbidden();

        $this->actingAs($account, 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'settings-page']))
            ->assertForbidden();

        // Настройки базы — тоже только администратору, даже POST-ом по адресу.
        $this->actingAs($account, 'moonshine')
            ->post(route('brain.admin.settings'), [])
            ->assertForbidden();
    }

    /** (г) Свой токен: плейн показан один раз, в базе только хэш. */
    public function test_g_regular_user_issues_own_token(): void
    {
        $mine = $this->user('tokens@panel.test');
        $account = $this->panelAccount($mine);

        $this->actingAs($account, 'moonshine')
            ->get(route('moonshine.resource.page', [
                'resourceUri' => 'api-token-resource',
                'pageUri' => 'api-token-form-page',
            ]))
            ->assertOk()
            ->assertSee('Выпустить токен');

        $this->actingAs($account, 'moonshine')
            ->post(route('moonshine.crud.store', ['resourceUri' => 'api-token-resource']), [
                'name' => 'Ноутбук',
                'abilities' => ['read', 'write'],
            ])
            ->assertRedirect();

        $response = $this->actingAs($account, 'moonshine')
            ->get($this->tokenIndexUrl())
            ->assertOk()
            ->assertSee('Ноутбук')
            ->assertSee('второй раз мы его не покажем');

        preg_match('/nb-[A-Za-z0-9]{40}/', (string) $response->getContent(), $matches);

        $this->assertNotEmpty($matches, 'Плейн-токен не показан после выпуска.');
        $plain = $matches[0];

        // Токен принадлежит человеку, а в базе лежит только его хэш.
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $mine->getKey(),
            'token_hash' => TokenIssuer::hash($plain),
        ]);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $plain]);

        // Второй раз значение не показывается.
        $this->actingAs($account, 'moonshine')
            ->get($this->tokenIndexUrl())
            ->assertOk()
            ->assertDontSee($plain);

        // Выпущенный в панели токен открывает MCP и API.
        $this->postJson('/mcp/knowledge', self::MCP_INITIALIZE, $this->mcpHeaders($plain))
            ->assertSuccessful();

        $this->getJson('/api/v1/search?q=laravel', $this->mcpHeaders($plain))->assertOk();
    }

    /** Свои токены видны, чужие — нет. */
    public function test_regular_user_sees_only_own_tokens(): void
    {
        $mine = $this->user('mine@panel.test');
        $other = $this->user('other@panel.test', 'Другой человек');

        app(TokenIssuer::class)->issue($mine, 'Мой токен', ['read']);
        app(TokenIssuer::class)->issue($other, 'Чужой токен', ['read']);

        app(OwnerContext::class)->forget();

        $this->actingAs($this->panelAccount($mine), 'moonshine')
            ->get($this->tokenIndexUrl())
            ->assertOk()
            ->assertSee('Мой токен')
            ->assertDontSee('Чужой токен');
    }

    /** Администратор видит токены всех людей. */
    public function test_admin_sees_all_tokens(): void
    {
        $mine = $this->user('mine@panel.test');
        $other = $this->user('other@panel.test', 'Другой человек');

        app(TokenIssuer::class)->issue($mine, 'Мой токен', ['read']);
        app(TokenIssuer::class)->issue($other, 'Чужой токен', ['read']);

        app(OwnerContext::class)->forget();

        $this->actingAs($this->admin(), 'moonshine')
            ->get($this->tokenIndexUrl())
            ->assertOk()
            ->assertSee('Мой токен')
            ->assertSee('Чужой токен');
    }

    /** Отзыв: свой токен удаляется, чужой не находится. */
    public function test_regular_user_revokes_own_token_only(): void
    {
        $mine = $this->user('revoke@panel.test');
        $other = $this->user('foreign@panel.test', 'Другой человек');

        $own = app(TokenIssuer::class)->issue($mine, 'Мой токен', ['read']);
        $foreign = app(TokenIssuer::class)->issue($other, 'Чужой токен', ['read']);

        app(OwnerContext::class)->forget();

        $account = $this->panelAccount($mine);

        $this->actingAs($account, 'moonshine')
            ->delete(route('moonshine.crud.destroy', [
                'resourceUri' => 'api-token-resource',
                'resourceItem' => $foreign['token']->getKey(),
            ]))
            ->assertNotFound();

        $this->assertDatabaseHas('api_tokens', ['id' => $foreign['token']->getKey()]);

        $this->actingAs($account, 'moonshine')
            ->delete(route('moonshine.crud.destroy', [
                'resourceUri' => 'api-token-resource',
                'resourceItem' => $own['token']->getKey(),
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('api_tokens', ['id' => $own['token']->getKey()]);
    }

    /** Администратор не выпускает токены: это личное дело человека. */
    public function test_admin_cannot_issue_tokens(): void
    {
        $this->actingAs($this->admin(), 'moonshine')
            ->post(route('moonshine.crud.store', ['resourceUri' => 'api-token-resource']), [
                'name' => 'Чужой токен',
                'abilities' => ['read'],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('api_tokens', ['name' => 'Чужой токен']);
    }

    /** Человек, созданный из панели, сразу получает вход в панель. */
    public function test_person_created_from_the_panel_gets_a_panel_account(): void
    {
        $this->actingAs($this->admin(), 'moonshine')
            ->post(route('moonshine.crud.store', ['resourceUri' => 'user-resource']), [
                'name' => 'Новый человек',
                'email' => 'new-person@panel.test',
                'locale' => 'ru',
                'is_active' => '1',
                'password' => 'panel-pass',
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'new-person@panel.test')->firstOrFail();
        $account = MoonshineUser::query()->where('email', 'new-person@panel.test')->firstOrFail();

        $this->assertSame(PanelRole::User->value, (int) $account->moonshine_user_role_id);
        $this->assertSame((int) $user->getKey(), (int) $account->user_id);
        $this->assertTrue(Hash::check('panel-pass', $account->password));
        $this->assertTrue(Hash::check('panel-pass', $user->password));
    }

    /** (д) Старый адрес /login ведёт на вход в панель. */
    public function test_d_old_login_redirects_to_the_panel(): void
    {
        $this->get('/login')->assertRedirect('/admin/login');
    }
}
