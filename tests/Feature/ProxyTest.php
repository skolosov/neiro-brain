<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Services\Brain\SourceRegistry;
use App\Settings\ProxySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

    public function test_empty_tool_schema_is_normalized_for_strict_gateways(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'deepseek-flash',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'ok'],
                    'finish_reason' => 'stop',
                ]],
            ]),
        ]);

        $response = $this->withHeaders([
            'X-Neiro-Proxy-Token' => 'dev-proxy-token',
            'X-Neiro-Upstream' => 'deepseek',
            'Authorization' => 'Bearer sk-from-harness',
        ])->postJson('/v1/chat/completions', [
            'model' => 'deepseek-flash',
            'messages' => [['role' => 'user', 'content' => 'test']],
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'get_goal',
                    'description' => 'Get the current goal',
                    'parameters' => [],
                ],
            ]],
        ]);

        $response->assertOk();

        Http::assertSent(function (Request $request): bool {
            $parameters = $request->data()['tools'][0]['function']['parameters'] ?? null;

            return is_array($parameters)
                && ($parameters['type'] ?? null) === 'object'
                && array_key_exists('properties', $parameters);
        });
    }

    public function test_provider_key_and_upstream_can_come_from_the_harness(): void
    {
        $settings = app(ProxySettings::class);
        $settings->upstream = 'mock';
        $settings->base_url = '';
        $settings->api_key = '';
        $settings->save();

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'deepseek-flash',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'ok'],
                    'finish_reason' => 'stop',
                ]],
            ]),
        ]);

        $response = $this->withHeaders([
            'X-Neiro-Proxy-Token' => 'dev-proxy-token',
            'X-Neiro-Upstream' => 'deepseek',
            'Authorization' => 'Bearer sk-from-harness',
            'X-Neiro-Session' => 'harness-key',
        ])->postJson('/v1/chat/completions', [
            'model' => 'deepseek-flash',
            'reasoning_effort' => 'max',
            'messages' => [['role' => 'user', 'content' => 'test']],
        ]);

        $response->assertOk();

        Http::assertSent(function (Request $request): bool {
            $authorization = $request->headers()['Authorization'][0] ?? null;

            return $request->url() === 'https://api.deepseek.com/chat/completions'
                && $authorization === 'Bearer sk-from-harness'
                && ($request->data()['reasoning_effort'] ?? null) === 'max';
        });
    }

    public function test_harness_controls_model_and_reasoning_settings(): void
    {
        $settings = app(ProxySettings::class);
        $settings->upstream = 'openai';
        $settings->base_url = 'https://upstream.test/v1';
        $settings->api_key = 'test-key';
        $settings->model = 'admin-default-model';
        $settings->save();

        Http::fake([
            'https://upstream.test/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'harness-model',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'ok'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
            ]),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer dev-proxy-token',
            'X-Neiro-Session' => 'settings-passthrough',
        ])->postJson('/v1/chat/completions', [
            'model' => 'harness-model',
            'reasoning_effort' => 'high',
            'thinking' => ['type' => 'enabled'],
            'temperature' => 0.35,
            'messages' => [['role' => 'user', 'content' => 'test']],
        ]);

        $response->assertOk();

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'https://upstream.test/v1/chat/completions'
                && ($data['model'] ?? null) === 'harness-model'
                && ($data['reasoning_effort'] ?? null) === 'high'
                && ($data['thinking']['type'] ?? null) === 'enabled'
                && ($data['temperature'] ?? null) === 0.35;
        });
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

    public function test_openai_responses_endpoint_works(): void
    {
        $this->seedKnowledge();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer dev-proxy-token',
            'X-Neiro-Session' => 'responses-session',
        ])->postJson('/v1/responses', [
            'model' => 'gpt-test',
            'input' => 'Какой у меня стек?',
        ]);

        $response->assertOk();
        $this->assertSame('response', $response->json('object'));
        $this->assertStringContainsString('Основной стек', $response->json('output.0.content.0.text'));
        $this->assertGreaterThanOrEqual(1, (int) $response->headers->get('X-Neiro-Memory-Hits'));
        $this->assertDatabaseHas('conversations', ['external_id' => 'proxy:responses-session']);
    }

    public function test_openai_responses_streaming_works(): void
    {
        $this->seedKnowledge();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer dev-proxy-token',
            'X-Neiro-Session' => 'responses-stream',
        ])->postJson('/v1/responses', [
            'model' => 'gpt-test',
            'input' => 'Какой у меня стек?',
            'stream' => true,
        ]);

        $content = $response->streamedContent();

        $this->assertStringContainsString('response.output_text.delta', $content);
        $this->assertStringContainsString('response.completed', $content);
        $this->assertSame(2, Message::query()->count());
    }

    public function test_anthropic_count_tokens_passthrough(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer dev-proxy-token',
        ])->postJson('/v1/messages/count_tokens', [
            'model' => 'claude-test',
            'messages' => [['role' => 'user', 'content' => 'Привет']],
        ]);

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('input_tokens'));
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

    public function test_proxy_forwards_the_harness_key_upstream(): void
    {
        Http::fake([
            'https://api.deepseek.com/anthropic/v1/messages' => Http::response([
                'id' => 'msg_test',
                'type' => 'message',
                'role' => 'assistant',
                'model' => 'deepseek-flash',
                'content' => [['type' => 'text', 'text' => 'ok']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->withHeaders(['x-api-key' => 'sk-harness-key'])
            ->postJson('/dev-proxy-token/v1/messages', [
                'model' => 'deepseek-flash',
                'max_tokens' => 8,
                'messages' => [['role' => 'user', 'content' => 'ping']],
            ])
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.deepseek.com/anthropic/v1/messages'
            && ($request->header('x-api-key')[0] ?? '') === 'sk-harness-key');
    }

    public function test_null_text_blocks_are_normalized_before_upstream(): void
    {
        Http::fake([
            'https://api.deepseek.com/anthropic/v1/messages' => Http::response([
                'id' => 'msg_test',
                'type' => 'message',
                'role' => 'assistant',
                'model' => 'deepseek-flash',
                'content' => [['type' => 'text', 'text' => 'ok']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->withHeaders([
            'X-Neiro-Proxy-Token' => 'dev-proxy-token',
            'x-api-key' => 'sk-harness-key',
        ])
            ->postJson('/v1/messages', [
                'model' => 'deepseek-flash',
                'max_tokens' => 8,
                'messages' => [
                    ['role' => 'user', 'content' => 'hi'],
                    ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => null]]],
                    ['role' => 'user', 'content' => 'go'],
                ],
            ])
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => (($request->data()['messages'][1]['content'][0]['text'] ?? null) === ''));
    }

    public function test_token_in_the_path_authorizes_the_proxy(): void
    {
        $this->getJson('/dev-proxy-token/v1/models')->assertOk();
        $this->getJson('/not-a-token/v1/models')->assertUnauthorized();
        $this->getJson('/v1/models')->assertUnauthorized();
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

    public function test_null_message_content_is_normalized_before_upstream(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'deepseek-flash',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'ok'],
                    'finish_reason' => 'stop',
                ]],
            ]),
        ]);

        $response = $this->withHeaders([
            'X-Neiro-Proxy-Token' => 'dev-proxy-token',
            'X-Neiro-Upstream' => 'deepseek',
            'Authorization' => 'Bearer sk-from-harness',
        ])->postJson('/v1/chat/completions', [
            'model' => 'deepseek-flash',
            'messages' => [
                ['role' => 'user', 'content' => 'test'],
                ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => ['name' => 'bash', 'arguments' => '{}'],
                ]]],
                ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => null],
                ['role' => 'user', 'content' => 'done'],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function (Request $request): bool {
            foreach ($request->data()['messages'] ?? [] as $message) {
                if (! is_string($message['content'] ?? null)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_null_content_is_normalized_on_every_message_endpoint(): void
    {
        Http::fake([
            'https://api.deepseek.com/*' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'deepseek-flash',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'ok'],
                    'finish_reason' => 'stop',
                ]],
            ]),
        ]);

        $headers = [
            'X-Neiro-Proxy-Token' => 'dev-proxy-token',
            'X-Neiro-Upstream' => 'deepseek',
            'Authorization' => 'Bearer sk-from-harness',
        ];

        $messages = [
            ['role' => 'user', 'content' => 'test'],
            ['role' => 'assistant', 'content' => null, 'tool_calls' => []],
            ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => null],
        ];

        $this->withHeaders($headers)->postJson('/v1/responses', [
            'model' => 'deepseek-flash',
            'messages' => $messages,
        ])->assertOk();

        $this->withHeaders($headers)->postJson('/v1/responses', [
            'model' => 'deepseek-flash',
            'input' => $messages,
        ])->assertOk();

        $this->withHeaders($headers)->postJson('/v1/messages', [
            'model' => 'deepseek-flash',
            'max_tokens' => 10,
            'messages' => [
                ['role' => 'assistant', 'content' => null],
                ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => null]]],
                ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'call_1', 'content' => null]]],
            ],
        ])->assertOk();

        Http::assertNotSent(function (Request $request): bool {
            foreach (['messages', 'input'] as $key) {
                foreach ($request->data()[$key] ?? [] as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    if (array_key_exists('content', $message) && $message['content'] === null) {
                        return true;
                    }

                    foreach ((array) ($message['content'] ?? []) as $block) {
                        if (is_array($block) && array_key_exists('text', $block) && $block['text'] === null) {
                            return true;
                        }

                        if (is_array($block) && array_key_exists('content', $block) && $block['content'] === null) {
                            return true;
                        }
                    }
                }
            }

            return false;
        });
    }

    public function test_normalized_null_count_is_reported_in_the_header(): void
    {
        Http::fake([
            'https://api.deepseek.com/*' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'deepseek-flash',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'ok'],
                    'finish_reason' => 'stop',
                ]],
            ]),
        ]);

        $response = $this->withHeaders([
            'X-Neiro-Proxy-Token' => 'dev-proxy-token',
            'X-Neiro-Upstream' => 'deepseek',
            'Authorization' => 'Bearer sk-from-harness',
        ])->postJson('/v1/chat/completions', [
            'model' => 'deepseek-flash',
            'messages' => [
                ['role' => 'user', 'content' => 'test'],
                ['role' => 'assistant', 'content' => null],
                ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => null],
            ],
        ]);

        $response->assertOk();
        $this->assertSame('2', $response->headers->get('X-Neiro-Normalized'));
    }
}
