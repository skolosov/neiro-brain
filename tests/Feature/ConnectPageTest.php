<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Auth\TokenIssuer;
use App\Support\OwnerContext;
use App\Support\PanelAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

/**
 * Ссылка `/connect`, которую печатает `/brain init`: человек открывает её в
 * браузере, выпускает токен и копирует готовый конфиг. Токен не проходит через
 * переписку с агентом.
 */
class ConnectPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_the_panel_login(): void
    {
        $this->get('/connect')
            ->assertRedirect(route('moonshine.page', ['pageUri' => 'connect-page']));

        $this->get(route('moonshine.page', ['pageUri' => 'connect-page']))
            ->assertRedirect();

        $location = (string) $this->get(route('moonshine.page', ['pageUri' => 'connect-page']))
            ->headers->get('Location');

        $this->assertStringContainsString('/admin/login', $location);
    }

    public function test_page_offers_to_issue_a_token(): void
    {
        $this->actingAs($this->panelAccount(), 'moonshine')
            ->get($this->pageUrl())
            ->assertOk()
            ->assertSee('Выпустить токен и показать конфиг');
    }

    public function test_issuing_shows_the_config_once(): void
    {
        $account = $this->panelAccount();

        $this->actingAs($account, 'moonshine')
            ->post('/connect', ['name' => 'Ноутбук'])
            ->assertRedirect();

        $page = $this->actingAs($account, 'moonshine')->get($this->pageUrl());
        $page->assertOk()
            ->assertSee('Скопировать конфиг')
            ->assertSee('mcpServers', false)
            ->assertSee(rtrim((string) config('app.url'), '/').'/mcp/knowledge', false);

        $content = (string) $page->getContent();
        preg_match('/nb-[A-Za-z0-9]{40}/', $content, $matches);
        $this->assertNotEmpty($matches, 'Плейн-токен не показан на странице подключения.');

        $plain = $matches[0];
        $ownerId = PanelAccess::ownerId();

        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $ownerId,
            'name' => 'Ноутбук',
            'token_hash' => TokenIssuer::hash($plain),
        ]);

        // Второй заход на страницу токен уже не показывает: он живёт только во flash.
        $this->actingAs($account, 'moonshine')
            ->get($this->pageUrl())
            ->assertOk()
            ->assertDontSee($plain);
    }

    public function test_token_works_for_mcp(): void
    {
        $account = $this->panelAccount();

        $this->actingAs($account, 'moonshine')->post('/connect', ['name' => 'Харнесс'])->assertRedirect();

        $page = (string) $this->actingAs($account, 'moonshine')->get($this->pageUrl())->getContent();
        preg_match('/nb-[A-Za-z0-9]{40}/', $page, $matches);

        $this->assertNotEmpty($matches);

        $this->postJson('/mcp/knowledge', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
        ], $this->mcpHeaders($matches[0]))->assertSuccessful();
    }

    private function pageUrl(): string
    {
        return route('moonshine.page', ['pageUri' => 'connect-page']);
    }

    private function panelAccount(): MoonshineUser
    {
        $owner = $this->owner();
        app(OwnerContext::class)->forget();

        $account = MoonshineUser::query()->create([
            'name' => 'Пользователь панели',
            'email' => 'connect@panel.test',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => 2,
        ]);

        // user_id не в fillable у MoonShine-модели, связку ставим явно.
        $account->forceFill(['user_id' => $owner->getKey()])->save();

        return $account;
    }
}
