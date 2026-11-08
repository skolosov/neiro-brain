<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Services\Brain\SourceRegistry;
use App\Settings\ProxySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function entry(): KnowledgeEntry
    {
        return app(KnowledgeWriter::class)->write(
            new ExtractedEntry(
                kind: KnowledgeKind::Stack,
                title: 'Основной стек',
                content: 'Laravel 13, PostgreSQL, MoonShine.',
                project: 'neiro-brain',
                importance: 4,
                confidence: 1.0,
            ),
            source: app(SourceRegistry::class)->manual(),
        )['entry'];
    }

    public function test_dashboard_renders_for_admin(): void
    {
        $this->actingAs($this->admin(), 'moonshine')
            ->get(route('moonshine.index'))
            ->assertOk()
            ->assertSee('Neiro Brain')
            ->assertSee('Синхронизировать');
    }

    public function test_knowledge_entry_pages_render(): void
    {
        $entry = $this->entry();
        $admin = $this->admin();

        $this->actingAs($admin, 'moonshine')
            ->get(route('moonshine.resource.page', [
                'resourceUri' => 'knowledge-entry-resource',
                'pageUri' => 'knowledge-entry-index-page',
            ]))
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

    public function test_settings_page_renders(): void
    {
        $this->actingAs($this->admin(), 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'settings-page']))
            ->assertOk()
            ->assertSee('Настройки Neiro Brain')
            ->assertSee('Токены доступа')
            ->assertSee('Поиск')
            ->assertSee('Суммаризация')
            ->assertDontSee('Upstream');
    }

    public function test_admin_can_generate_a_new_proxy_token(): void
    {
        $this->actingAs($this->admin(), 'moonshine')
            ->post(route('brain.admin.tokens.generate'), ['action_generate_token' => 'proxy'])
            ->assertRedirect();

        $token = app(ProxySettings::class)->refresh()->access_token;

        $this->assertNotSame('dev-proxy-token', $token);
        $this->assertStringStartsWith('nb-', $token);
    }

    public function test_generated_token_authorizes_the_proxy(): void
    {
        $this->actingAs($this->admin(), 'moonshine')
            ->post(route('brain.admin.tokens.generate'), ['action_generate_token' => 'proxy']);

        $token = app(ProxySettings::class)->refresh()->access_token;

        $this->withHeaders(['X-Neiro-Proxy-Token' => $token])
            ->postJson('/v1/chat/completions', [
                'model' => 'test',
                'messages' => [['role' => 'user', 'content' => 'ping']],
            ])
            ->assertOk();

        $this->withHeaders(['X-Neiro-Proxy-Token' => 'dev-proxy-token'])
            ->postJson('/v1/chat/completions', [
                'model' => 'test',
                'messages' => [['role' => 'user', 'content' => 'ping']],
            ])
            ->assertUnauthorized();
    }

    public function test_guest_is_redirected_from_admin(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_sync_button_route_is_protected_by_admin_auth(): void
    {
        $this->post('/admin/brain/sync')->assertRedirect();
    }
}
