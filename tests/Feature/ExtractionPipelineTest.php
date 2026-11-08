<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\KnowledgeExtractionAgent;
use App\Services\Brain\ConversationIngestor;
use App\Services\Brain\ExtractionPipeline;
use App\Services\Brain\SourceRegistry;
use App\Settings\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtractionPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_structured_output_is_parsed_into_entries(): void
    {
        $ai = app(AiSettings::class);
        $ai->enabled = true;
        $ai->provider = 'openai';
        $ai->model = 'gpt-test';
        $ai->api_key = 'test-key';
        $ai->save();

        KnowledgeExtractionAgent::fake([
            [
                'entries' => [[
                    'kind' => 'preference',
                    'title' => 'Любит Pest',
                    'content' => 'Пользователь пишет тесты на Pest.',
                    'details' => '',
                    'project' => 'neiro-brain',
                    'tags' => ['testing'],
                    'importance' => 4,
                    'confidence' => 0.9,
                ]],
            ],
        ]);

        $source = app(SourceRegistry::class)->proxy();
        $conversation = app(ConversationIngestor::class)->ingest(
            $source,
            'ai-pipeline-test',
            [
                ['role' => 'user', 'content' => 'Я пишу тесты на Pest и люблю строгие типы.'],
            ],
        )['conversation'];

        $result = app(ExtractionPipeline::class)->extract($conversation);

        $this->assertSame('ai:openai:gpt-test', $result['engine']);
        $this->assertCount(1, $result['entries']);
        $this->assertSame('Любит Pest', $result['entries'][0]->title);
        $this->assertSame(4, $result['entries'][0]->importance);
    }

    public function test_heuristic_fallback_is_used_without_api_key(): void
    {
        $ai = app(AiSettings::class);
        $ai->enabled = true;
        $ai->api_key = '';
        $ai->save();

        $source = app(SourceRegistry::class)->proxy();
        $conversation = app(ConversationIngestor::class)->ingest(
            $source,
            'heuristic-pipeline-test',
            [
                ['role' => 'user', 'content' => 'Я предпочитаю Laravel 13 и PostgreSQL для всех новых проектов.'],
                ['role' => 'assistant', 'content' => 'Записал стек.'],
            ],
        )['conversation'];

        $result = app(ExtractionPipeline::class)->extract($conversation);

        $this->assertSame('heuristic', $result['engine']);
        $this->assertNotEmpty($result['entries']);
    }
}
