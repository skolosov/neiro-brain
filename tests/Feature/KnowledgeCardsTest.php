<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Models\KnowledgeEntry;
use App\Services\Brain\BrainGraph;
use App\Services\Brain\CardSearch;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Services\Brain\TopicMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

class KnowledgeCardsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'name' => 'Admin',
            'email' => 'cards@example.test',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => 1,
        ]);
    }

    /**
     * @param  list<string>  $tags
     */
    private function write(
        string $title,
        string $content,
        string $kind = 'fact',
        ?string $project = null,
        array $tags = [],
        int $importance = 4,
    ): KnowledgeEntry {
        return app(KnowledgeWriter::class)->write(
            new ExtractedEntry(
                kind: KnowledgeKind::from($kind),
                title: $title,
                content: $content,
                project: $project,
                tags: $tags,
                importance: $importance,
                confidence: 1.0,
            ),
        )['entry'];
    }

    public function test_topics_are_derived_from_tags_and_kind(): void
    {
        $tagged = $this->write(
            'BM25 вместо векторной базы',
            'Для Neiro Brain решили начать с BM25, потому что нужна объяснимость результата.',
            kind: 'decision',
            tags: ['bm25', 'architecture'],
        );

        $untagged = $this->write(
            'Тесты на Pest',
            'Пользователь пишет тесты на Pest и покрывает граничные случаи.',
            kind: 'style',
        );

        $topics = app(TopicMap::class);

        // Broader themes come first: "Архитектура" outranks the narrower "Поиск".
        $this->assertSame(['Архитектура', 'Поиск'], $topics->of($tagged));
        $this->assertSame(['Стиль'], $topics->of($untagged));

        $counts = $topics->counts();

        $this->assertSame(1, $counts['Поиск']);
        $this->assertSame(1, $counts['Стиль']);
    }

    public function test_graph_links_projects_topics_and_entries(): void
    {
        $entry = $this->write(
            'BM25 вместо векторной базы',
            'Для Neiro Brain решили начать с BM25, потому что нужна объяснимость результата.',
            kind: 'decision',
            project: 'neiro-brain',
            tags: ['bm25'],
        );

        $graph = app(BrainGraph::class)->build()['graph']->toArray();
        $types = array_column($graph['nodes'], 'type');
        $ids = array_column($graph['nodes'], 'id');

        $this->assertContains('topic', $types);
        $this->assertContains('entry', $types);
        $this->assertContains('tПоиск', $ids);
        $this->assertContains('e'.$entry->id, $ids);

        // One project is not a comparison: it would turn the graph into a star,
        // so the project layer appears only from the second project on.
        $this->assertNotContains('project', $types);
    }

    public function test_graph_shows_the_project_layer_for_several_projects(): void
    {
        $this->write(
            'Стек Sklad',
            'В Sklad выбрали Laravel 13 и PostgreSQL для учёта остатков на складе.',
            kind: 'stack',
            project: 'Sklad',
            tags: ['laravel'],
        );

        $this->write(
            'Стек CRM',
            'В CRM выбрали Vue 3 и Inertia, чтобы не плодить отдельный API-слой.',
            kind: 'stack',
            project: 'CRM',
            tags: ['vue'],
        );

        $graph = app(BrainGraph::class)->build()['graph']->toArray();
        $ids = array_column($graph['nodes'], 'id');

        $this->assertContains('pSklad', $ids);
        $this->assertContains('pCRM', $ids);
    }

    public function test_search_page_shows_cards_topics_and_entry_panel(): void
    {
        $this->write(
            'Модульный монолит',
            'Пользователь предпочитает модульный монолит вместо микросервисов в маленькой команде.',
            kind: 'preference',
            project: 'neiro-brain',
            tags: ['архитектура'],
        );

        $this->actingAs($this->admin(), 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'search-page', 'q' => 'монолит']))
            ->assertOk()
            ->assertSee('Модульный монолит')
            ->assertSee('Редактировать')
            ->assertSee('Темы');
    }

    public function test_search_page_topic_mode_groups_cards_by_topic(): void
    {
        $this->write(
            'Импорт из 1С',
            'В Sklad импорт из 1С идемпотентный: повторный запуск не создаёт дубли накладных.',
            kind: 'decision',
            project: 'Sklad',
            tags: ['интеграции'],
        );

        $this->actingAs($this->admin(), 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'search-page', 'topic' => 'Прокси']))
            ->assertOk()
            ->assertSee('Импорт из 1С')
            ->assertSee('Тема «Прокси»');
    }

    public function test_graph_page_renders_canvas_and_data(): void
    {
        $this->write(
            'BM25 вместо векторной базы',
            'Для Neiro Brain решили начать с BM25, потому что нужна объяснимость результата.',
            kind: 'decision',
            project: 'neiro-brain',
            tags: ['поиск'],
        );

        $response = $this->actingAs($this->admin(), 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'graph-page']))
            ->assertOk()
            ->assertSee('Граф знаний');

        $this->assertStringContainsString('nb-graph-canvas', $response->getContent());
        $this->assertStringContainsString('nb-graph-data', $response->getContent());
    }

    public function test_dashboard_and_projects_pages_render_with_data(): void
    {
        $this->write(
            'Импорт из 1С',
            'В Sklad импорт из 1С идемпотентный: повторный запуск не создаёт дубли накладных.',
            kind: 'decision',
            project: 'Sklad',
            tags: ['интеграции'],
        );

        $admin = $this->admin();

        $this->actingAs($admin, 'moonshine')
            ->get(route('moonshine.index'))
            ->assertOk()
            ->assertSee('Что лежит в базе')
            ->assertSee('Темы')
            ->assertSee('Чанков');

        $this->actingAs($admin, 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'projects-page']))
            ->assertOk()
            ->assertSee('Sklad')
            ->assertSee('Прокси');
    }

    public function test_pages_render_on_an_empty_base(): void
    {
        $admin = $this->admin();

        foreach (['search-page', 'graph-page', 'projects-page'] as $pageUri) {
            $this->actingAs($admin, 'moonshine')
                ->get(route('moonshine.page', ['pageUri' => $pageUri]))
                ->assertOk();
        }

        $this->actingAs($admin, 'moonshine')
            ->get(route('moonshine.index'))
            ->assertOk()
            ->assertSee('Что лежит в базе');
    }

    public function test_card_panel_carries_the_summary_and_the_text(): void
    {
        $entry = $this->write(
            'Импорт из 1С',
            'В Sklad импорт из 1С должен быть идемпотентным: повторный импорт не создаёт дубли накладных.',
            kind: 'decision',
            project: 'Sklad',
            tags: ['интеграции'],
        );

        $card = app(CardSearch::class)->cardFor($entry);

        // The card is read as a document: a summary to skim, the text to read.
        $this->assertNotEmpty($card['summary']);
        $this->assertNotEmpty($card['keywords']);
        $this->assertSame('Sklad', $card['project']);
        $this->assertStringContainsString('1С', $card['content']);
        $this->assertArrayNotHasKey('atoms', $card);
    }
}
