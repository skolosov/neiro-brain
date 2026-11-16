<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KnowledgeEntry;
use App\Support\OwnerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

/**
 * API не должен терять тему карточки, а панель карточки — показывать поля,
 * которых больше нет (источник удалён вместе с прокси).
 */
class ApiThemeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'name' => 'Admin',
            'email' => 'theme@example.test',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => 1,
        ]);
    }

    public function test_api_keeps_the_theme_and_normalises_it(): void
    {
        $this->withOwner($this->owner());

        $this->postJson('/api/v1/entries', [
            'title' => 'Карточка с темой',
            'content' => 'Текст карточки, чтобы запись прошла.',
            'theme' => 'архитектура',
            'project' => 'neiro-brain',
            'scope' => 'project',
        ])->assertCreated();

        $this->assertDatabaseHas('knowledge_entries', [
            'title' => 'Карточка с темой',
            'theme' => 'architecture',
        ]);
    }

    public function test_card_panel_has_no_source_field(): void
    {
        $this->withOwner($this->owner());

        $this->postJson('/api/v1/entries', [
            'title' => 'Карточка без источника',
            'content' => 'Текст карточки, чтобы запись прошла.',
        ])->assertCreated();

        $entry = KnowledgeEntry::query()->where('title', 'Карточка без источника')->firstOrFail();

        app(OwnerContext::class)->forget();

        $this->actingAs($this->admin(), 'moonshine')
            ->get(route('moonshine.page', [
                'pageUri' => 'search-page',
                'entry' => $entry->getKey(),
            ]))
            ->assertOk()
            ->assertSee('Карточка без источника')
            ->assertDontSee('Источник');
    }
}
