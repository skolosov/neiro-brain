<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KnowledgeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API не должен терять тему карточки, а панель карточки — показывать поля,
 * которых больше нет (источник удалён вместе с прокси).
 */
class ApiThemeTest extends TestCase
{
    use RefreshDatabase;

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

        $this->actingAs($this->owner())
            ->get('/app/entries/'.$entry->getKey())
            ->assertOk()
            ->assertDontSee('Источник');
    }
}
