<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\KnowledgeSearch;
use App\Services\Brain\KnowledgeWriter;
use App\Services\Brain\SourceRegistry;
use App\Settings\RetrievalSettings;
use App\Settings\SyncSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Bm25SearchTest extends TestCase
{
    use RefreshDatabase;

    private function write(string $title, string $content, string $project = 'test', int $importance = 3): int
    {
        $result = app(KnowledgeWriter::class)->write(
            new ExtractedEntry(
                kind: KnowledgeKind::Fact,
                title: $title,
                content: $content,
                project: $project,
                importance: $importance,
                confidence: 1.0,
            ),
            source: app(SourceRegistry::class)->manual(),
        );

        return $result['entry']->id;
    }

    public function test_rare_term_outranks_a_document_that_only_has_the_common_term(): void
    {
        $rare = $this->write('Борщ', 'Классический борщ со свёклой и капустой.');
        $common = $this->write('Приготовление', 'Как приготовить простое блюдо на ужин. Приготовить быстро.');

        $result = app(KnowledgeSearch::class)->search(new SearchQuery('приготовить борщ'), 'test');

        $this->assertNotEmpty($result->hits);
        $this->assertSame($rare, $result->hits[0]->entry->id);
        $this->assertNotSame($common, $result->hits[0]->entry->id);
    }

    public function test_shorter_document_wins_when_term_frequency_is_equal(): void
    {
        $short = $this->write('Коротко', 'Laravel.');
        $long = $this->write('Длинно', 'Laravel. '.str_repeat('Посторонний текст без ключевых слов. ', 80));

        $result = app(KnowledgeSearch::class)->search(new SearchQuery('Laravel'), 'test');

        $this->assertSame($short, $result->hits[0]->entry->id);
        $this->assertNotSame($long, $result->hits[0]->entry->id);
    }

    public function test_project_filter_is_applied(): void
    {
        $this->write('Sklad stack', 'Laravel PostgreSQL Redis', 'Sklad');
        $this->write('CRM stack', 'Laravel PostgreSQL Inertia', 'CRM');

        $result = app(KnowledgeSearch::class)->search(new SearchQuery('Laravel', project: 'Sklad'), 'test');

        $this->assertCount(1, $result->hits);
        $this->assertSame('Sklad', $result->hits[0]->entry->project);
    }

    public function test_drafts_are_excluded_by_default(): void
    {
        $sync = app(SyncSettings::class);
        $sync->auto_approve = false;
        $sync->save();

        app(KnowledgeWriter::class)->write(
            new ExtractedEntry(
                kind: KnowledgeKind::Fact,
                title: 'Черновик',
                content: 'Уникальное слово черновик',
            ),
            source: app(SourceRegistry::class)->manual(),
        );

        $result = app(KnowledgeSearch::class)->search(new SearchQuery('черновик'), 'test');

        $this->assertTrue($result->isEmpty());

        $result = app(KnowledgeSearch::class)->search(new SearchQuery('черновик', includeDrafts: true), 'test');
        $this->assertFalse($result->isEmpty());
    }

    public function test_russian_morphology_matches(): void
    {
        $id = $this->write('Стек', 'Мы используем Laravel и PostgreSQL для проектов.');

        $result = app(KnowledgeSearch::class)->search(new SearchQuery('проекта Laravel'), 'test');

        $this->assertSame($id, $result->hits[0]->entry->id ?? null);
    }

    public function test_k1_and_b_are_configurable(): void
    {
        $settings = app(RetrievalSettings::class);
        $settings->k1 = 1.6;
        $settings->b = 0.4;
        $settings->save();

        $this->write('Настройка', 'Laravel настраиваемый поиск');

        $result = app(KnowledgeSearch::class)->search(new SearchQuery('Laravel'), 'test');

        $this->assertNotEmpty($result->hits);
    }
}
