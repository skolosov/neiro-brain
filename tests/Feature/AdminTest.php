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
use Illuminate\Support\Facades\Hash;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => 1,
        ]);
    }

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

    private function entryFor(User $owner, string $title, string $content = 'Laravel 13, PostgreSQL, MoonShine.'): KnowledgeEntry
    {
        app(OwnerContext::class)->set($owner);

        return app(KnowledgeWriter::class)->write(new ExtractedEntry(
            kind: KnowledgeKind::Stack,
            title: $title,
            content: $content,
            project: 'neiro-brain',
            scope: KnowledgeScope::Project,
            importance: 4,
            confidence: 1.0,
        ))['entry'];
    }

    private function knowledgeIndexUrl(array $query = []): string
    {
        return route('moonshine.resource.page', [
            'resourceUri' => 'knowledge-entry-resource',
            'pageUri' => 'knowledge-entry-index-page',
            ...$query,
        ]);
    }

    public function test_dashboard_renders_for_admin(): void
    {
        app(OwnerContext::class)->forget();

        $this->actingAs($this->admin(), 'moonshine')
            ->get(route('moonshine.index'))
            ->assertOk()
            ->assertSee('Neiro Brain')
            ->assertSee('Карточек');
    }

    public function test_admin_screens_render(): void
    {
        app(OwnerContext::class)->forget();
        $admin = $this->admin();

        $this->actingAs($admin, 'moonshine')
            ->get($this->knowledgeIndexUrl())
            ->assertOk();

        $this->actingAs($admin, 'moonshine')
            ->get(route('moonshine.resource.page', [
                'resourceUri' => 'user-resource',
                'pageUri' => 'user-index-page',
            ]))
            ->assertOk()
            ->assertSee('Люди')
            ->assertSee('каскадно удалит');

        $this->actingAs($admin, 'moonshine')
            ->get(route('moonshine.resource.page', [
                'resourceUri' => 'api-token-resource',
                'pageUri' => 'api-token-index-page',
            ]))
            ->assertOk()
            ->assertSee('Токены');

        $this->actingAs($admin, 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'graph-page']))
            ->assertOk()
            ->assertSee('Граф знаний');

        $this->actingAs($admin, 'moonshine')
            ->get(route('moonshine.resource.page', [
                'resourceUri' => 'user-resource',
                'pageUri' => 'user-form-page',
            ]))
            ->assertOk()
            ->assertSee('Человек');
    }

    public function test_knowledge_entry_pages_render(): void
    {
        $entry = $this->entryFor($this->owner(), 'Основной стек');
        app(OwnerContext::class)->forget();
        $admin = $this->admin();

        $this->actingAs($admin, 'moonshine')
            ->get($this->knowledgeIndexUrl())
            ->assertOk()
            ->assertSee('Основной стек');

        $this->actingAs($admin, 'moonshine')
            ->get(route('moonshine.resource.page', [
                'resourceUri' => 'knowledge-entry-resource',
                'pageUri' => 'knowledge-entry-detail-page',
                'resourceItem' => $entry->id,
            ]))
            ->assertOk()
            ->assertSee('Основной стек');
    }

    public function test_admin_sees_cards_of_both_owners_and_filters_by_owner(): void
    {
        $first = $this->owner();
        $second = $this->user('second@example.test', 'Второй человек');

        $this->entryFor($first, 'Карточка первого владельца');
        $this->entryFor($second, 'Карточка второго владельца');

        app(OwnerContext::class)->forget();
        $admin = $this->admin();

        $this->actingAs($admin, 'moonshine')
            ->get($this->knowledgeIndexUrl())
            ->assertOk()
            ->assertSee('Карточка первого владельца')
            ->assertSee('Карточка второго владельца');

        $this->actingAs($admin, 'moonshine')
            ->get($this->knowledgeIndexUrl(['filter' => ['owner_id' => $first->id]]))
            ->assertOk()
            ->assertSee('Карточка первого владельца')
            ->assertDontSee('Карточка второго владельца');
    }

    public function test_settings_page_renders(): void
    {
        app(OwnerContext::class)->forget();

        $this->actingAs($this->admin(), 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'settings-page']))
            ->assertOk()
            ->assertSee('Настройки Neiro Brain')
            ->assertSee('Правила для агента')
            ->assertSee('Дополнительные правила для агента')
            ->assertSee('Поиск')
            ->assertSee('Насыщение повторов')
            ->assertDontSee('Суммаризация')
            ->assertDontSee('Синхронизация')
            ->assertDontSee('Upstream');
    }

    public function test_user_screen_creates_a_user_with_manual_password(): void
    {
        app(OwnerContext::class)->forget();
        $admin = $this->admin();

        $this->actingAs($admin, 'moonshine')
            ->post(route('moonshine.crud.store', ['resourceUri' => 'user-resource']), [
                'name' => 'Новый человек',
                'email' => 'new@example.test',
                'locale' => 'ru',
                'is_active' => '1',
                'password' => 'manual-password',
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'new@example.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('Новый человек', $user->name);
        $this->assertTrue(Hash::check('manual-password', $user->password));
    }

    public function test_user_screen_generates_a_password_when_it_is_empty(): void
    {
        app(OwnerContext::class)->forget();
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'moonshine')
            ->post(route('moonshine.crud.store', ['resourceUri' => 'user-resource']), [
                'name' => 'Без пароля',
                'email' => 'generated@example.test',
                'locale' => 'ru',
                'is_active' => '1',
                'password' => '',
            ])
            ->assertRedirect();

        $plain = $response->getSession()->get('user_password');

        $this->assertIsString($plain);
        $this->assertNotSame('', $plain);

        $user = User::query()->where('email', 'generated@example.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check($plain, $user->password));
    }

    public function test_admin_can_disable_a_user_without_deleting_cards(): void
    {
        $user = $this->user('disable@example.test', 'Отключаемый');
        $card = $this->entryFor($user, 'Карточка отключаемого');

        app(OwnerContext::class)->forget();
        $admin = $this->admin();

        $url = route('moonshine.method', [
            'pageUri' => 'user-index-page',
            'resourceUri' => 'user-resource',
            'method' => 'disableUser',
            'resourceItem' => $user->id,
        ]);

        $this->actingAs($admin, 'moonshine')
            ->post($url)
            ->assertOk();

        $user->refresh();

        $this->assertFalse($user->is_active);
        $this->assertDatabaseHas('knowledge_entries', ['id' => $card->id, 'owner_id' => $user->id]);
    }

    public function test_deleting_a_user_removes_only_his_cards_and_tokens(): void
    {
        $first = $this->owner();
        $second = $this->user('second@example.test', 'Второй человек');

        $this->entryFor($first, 'Карточка удаляемого');
        $this->entryFor($second, 'Карточка остающегося');
        app(TokenIssuer::class)->issue($first, 'Токен удаляемого', ['read']);

        app(OwnerContext::class)->forget();
        $admin = $this->admin();

        $this->actingAs($admin, 'moonshine')
            ->delete(route('moonshine.crud.destroy', [
                'resourceUri' => 'user-resource',
                'resourceItem' => $first->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $first->id]);
        $this->assertDatabaseMissing('knowledge_entries', ['owner_id' => $first->id]);
        $this->assertDatabaseMissing('api_tokens', ['user_id' => $first->id]);
        $this->assertDatabaseHas('knowledge_entries', ['owner_id' => $second->id]);
    }

    public function test_admin_sees_tokens_of_all_people_and_can_revoke(): void
    {
        $first = $this->owner();
        $second = $this->user('second@example.test', 'Второй человек');

        app(TokenIssuer::class)->issue($first, 'Токен первого', ['read']);
        $secondToken = app(TokenIssuer::class)->issue($second, 'Токен второго', ['read', 'write']);

        app(OwnerContext::class)->forget();
        $admin = $this->admin();

        $this->actingAs($admin, 'moonshine')
            ->get(route('moonshine.resource.page', [
                'resourceUri' => 'api-token-resource',
                'pageUri' => 'api-token-index-page',
            ]))
            ->assertOk()
            ->assertSee('Токен первого')
            ->assertSee('Токен второго')
            ->assertSee('Отозвать');

        $this->actingAs($admin, 'moonshine')
            ->delete(route('moonshine.crud.destroy', [
                'resourceUri' => 'api-token-resource',
                'resourceItem' => $secondToken['token']->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('api_tokens', ['id' => $secondToken['token']->id]);
    }

    public function test_guest_is_redirected_from_admin(): void
    {
        $this->get('/admin')->assertRedirect();
    }
}
