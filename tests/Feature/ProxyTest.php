<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Services\Brain\SourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxyTest extends TestCase
{
    use RefreshDatabase;

    private function seedKnowledge(): void
    {
        app(KnowledgeWriter::class)->write(
            new ExtractedEntry(
                kind: KnowledgeKind::Stack,
                title: 'Основной стек',
                content: 'Основной стек: Laravel 13, PostgreSQL 16, MoonShine, Vue 3.',
                project: 'neiro-brain',
                importance: 4,
                confidence: 1.0,
            ),
            source: app(SourceRegistry::class)->manual(),
        );
    }

    public function test_openai_proxy_injects_memory_and_records_conversation(): void
    {
        $this->seedKnowledge();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer dev-proxy-token',
            'X-Neiro-Session' => 'test-session',
            'X-Neiro-Harness' => 'test',
        ])->postJson('/v1/chat/completions', [
            'model' => 'gpt-test',
            'messages' => [
                ['role' => 'user', 'content' => 'Какой у меня стек?'],
            ],
        ]);

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, (int) $response->headers->get('X-Neiro-Memory-Hits'));
        $this->assertStringContainsString('Основной стек', $response->json('choices.0.message.content'));

        $this->assertDatabaseHas('conversations', [
            'external_id' => 'proxy:test-session',
            'harness' => 'test',
        ]);
        $this->assertSame(2, Message::query()->count());
        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_openai_streaming_returns_sse_and_captures_answer(): void
    {
        $this->seedKnowledge();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer dev-proxy-token',
            'X-Neiro-Session' => 'stream-session',
        ])->postJson('/v1/chat/completions', [
            'model' => 'gpt-test',
            'stream' => true,
            'messages' => [
                ['role' => 'user', 'content' => 'Что ты знаешь про мой стек?'],
            ],
        ]);

        $content = $response->streamedContent();

        $this->assertStringContainsString('data: [DONE]', $content);
        $this->assertStringContainsString('Neiro Brain', $content);
        $this->assertDatabaseHas('conversations', ['external_id' => 'proxy:stream-session']);
        $this->assertSame(2, Message::query()->count());
    }

    public function test_anthropic_messages_endpoint_works(): void
    {
        $this->seedKnowledge();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer dev-proxy-token',
            'X-Neiro-Session' => 'anthropic-session',
        ])->postJson('/v1/messages', [
            'model' => 'claude-test',
            'max_tokens' => 200,
            'messages' => [
                ['role' => 'user', 'content' => 'Какой у меня стек?'],
            ],
        ]);

        $response->assertOk();
        $this->assertSame('message', $response->json('type'));
        $this->assertStringContainsString('Основной стек', $response->json('content.0.text'));
        $this->assertGreaterThanOrEqual(1, (int) $response->headers->get('X-Neiro-Memory-Hits'));
    }

    public function test_proxy_rejects_invalid_token(): void
    {
        $this->postJson('/v1/chat/completions', [
            'model' => 'gpt-test',
            'messages' => [['role' => 'user', 'content' => 'test']],
        ], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    }

    public function test_memory_can_be_disabled_per_request(): void
    {
        $this->seedKnowledge();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer dev-proxy-token',
            'X-Neiro-Memory' => 'off',
            'X-Neiro-Session' => 'no-memory',
        ])->postJson('/v1/chat/completions', [
            'model' => 'gpt-test',
            'messages' => [['role' => 'user', 'content' => 'Какой у меня стек?']],
        ]);

        $response->assertOk();
        $this->assertSame('0', $response->headers->get('X-Neiro-Memory-Hits'));
        $this->assertStringNotContainsString('Основной стек', $response->json('choices.0.message.content'));
    }
}
