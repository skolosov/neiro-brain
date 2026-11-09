<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Models\KnowledgeEntry;
use App\Services\Brain\BrainGraph;
use App\Services\Brain\CardSearch;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\EntryQuality;
use App\Services\Brain\KnowledgeWriter;
use App\Services\Brain\SourceRegistry;
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
            source: app(SourceRegistry::class)->manual(),
        )['entry'];
    }

    public function test_quality_filter_drops_task_restatements_and_questions(): void
    {
        $quality = app(EntryQuality::class);

        $good = new ExtractedEntry(
            kind: KnowledgeKind::Preference,
            title: 'Модульный монолит',
            content: 'Пользователь предпочитает модульный монолит вместо микросервисов в маленькой команде.',
            importance: 4,
            confidence: 0.9,
        );

        $task = new ExtractedEntry(
            kind: KnowledgeKind::Fact,
            title: 'Распознать девять видео',
            content: 'Пользователь попросил распознать девять видео из папки Movies и перевести речь в текст.',
            importance: 3,
            confidence: 0.9,
        );

        $question = new ExtractedEntry(
            kind: KnowledgeKind::Fact,
            title: 'Вопрос про очередь',
            content: 'Стоит ли переводить очередь на database драйвер в этом проекте, ведь нагрузка растёт?',
            importance: 3,
            confidence: 0.8,
        );

        $noise = new ExtractedEntry(
            kind: KnowledgeKind::Other,
            title: 'Короткая реплика',
            content: 'Спасибо, всё получилось.',
            importance: 2,
            confidence: 0.5,
        );

        $this->assertNull($quality->reason($good));
        $this->assertSame('task', $quality->reason($task));
        $this->assertSame('question', $quality->reason($question));
        $this->assertSame('too_short', $quality->reason($noise));

        $filtered = $quality->filter([$good, $task, $question, $noise]);

        $this->assertCount(1, $filtered['entries']);
        $this->assertSame(3, array_sum($filtered['dropped']));
    }

    public function test_quality_filter_deduplicates_identical_entries(): void
    {
        $quality = app(EntryQuality::class);

        $first = new ExtractedEntry(
            kind: KnowledgeKind::Stack,
            title: 'Основной стек',
            content: 'Пользователь работает на Laravel 13 и PostgreSQL в локальном OpenServer.',
            importance: 4,
            confidence: 0.9,
        );

        $second = new ExtractedEntry(
            kind: KnowledgeKind::Stack,
            title: 'Основной стек',
            content: 'Пользователь работает на Laravel 13 и PostgreSQL в локальном OpenServer.',
            importance: 4,
            confidence: 0.9,
        );

        $filtered = $quality->filter([$first, $second]);

        $this->assertCount(1, $filtered['entries']);
        $this->assertSame(1, $filtered['dropped']['duplicate']);
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

        $this->assertSame(['Поиск', 'Архитектура'], $topics->of($tagged));
        $this->assertSame(['Стиль работы'], $topics->of($untagged));

        $counts = $topics->counts();

        $this->assertSame(1, $counts['Поиск']);
        $this->assertSame(1, $counts['Стиль работы']);
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

        $this->assertContains('project', $types);
        $this->assertContains('topic', $types);
        $this->assertContains('entry', $types);
        $this->assertContains('pneiro-brain', $ids);
        $this->assertContains('tПоиск', $ids);
        $this->assertContains('e'.$entry->id, $ids);
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
            ->get(route('moonshine.page', ['pageUri' => 'search-page', 'topic' => 'Интеграции']))
            ->assertOk()
            ->assertSee('Импорт из 1С')
            ->assertSee('Тема «Интеграции»');
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
            ->assertSee('Здоровье источников');

        $this->actingAs($admin, 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'projects-page']))
            ->assertOk()
            ->assertSee('Sklad')
            ->assertSee('Интеграции');
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

    public function test_card_panel_includes_atoms_and_keywords(): void
    {
        $entry = $this->write(
            'Импорт из 1С',
            'В Sklad импорт из 1С должен быть идемпотентным: повторный импорт не создаёт дубли накладных.',
            kind: 'decision',
            project: 'Sklad',
            tags: ['интеграции'],
        );

        $card = app(CardSearch::class)->cardFor($entry);

        $this->assertNotEmpty($card['atoms']);
        $this->assertNotEmpty($card['keywords']);
        $this->assertSame('Sklad', $card['project']);
        $this->assertStringContainsString('1С', $card['content']);
    }
}
