<?php

declare(strict_types=1);

namespace App\Http\Controllers\Proxy;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Brain\ContextBuilder;
use App\Services\Brain\ConversationIngestor;
use App\Services\Brain\Dto\SearchHit;
use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\KnowledgeSearch;
use App\Services\Brain\SourceRegistry;
use App\Services\Proxy\Dto\UpstreamConfig;
use App\Services\Proxy\MockUpstream;
use App\Services\Proxy\ToolSchemaNormalizer;
use App\Services\Proxy\UpstreamResolver;
use App\Settings\ProxySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Transparent OpenAI- and Anthropic-compatible proxy.
 *
 * A harness only changes its base URL (and token). Requests/responses keep
 * their original shape; the proxy quietly adds a memory block to the system
 * prompt and records the conversation for the next summarization run.
 */
class ProxyController extends Controller
{
    public function __construct(
        private readonly ProxySettings $settings,
        private readonly UpstreamResolver $resolver,
        private readonly MockUpstream $mock,
        private readonly KnowledgeSearch $search,
        private readonly ContextBuilder $context,
        private readonly SourceRegistry $sources,
        private readonly ConversationIngestor $ingestor,
        private readonly ToolSchemaNormalizer $toolSchemas,
    ) {}

    public function chatCompletions(Request $request): Response
    {
        $payload = $request->json()->all();

        if (! is_array($payload['messages'] ?? null) || $payload['messages'] === []) {
            return $this->error('The "messages" field is required.', 422, 'openai');
        }

        $upstream = $this->resolveUpstream($request, $payload, 'openai');
        $configurationError = $this->configurationError($upstream, 'openai');

        if ($configurationError !== null) {
            return $configurationError;
        }

        $lastUserText = $this->context->lastOpenAiUserText($payload['messages']);
        $conversation = $this->captureRequest($request, $payload, $lastUserText);
        $hits = $this->memoryHits($request, $lastUserText);
        $context = $this->context->buildHits($hits);

        if ($context !== '') {
            $payload['messages'] = $this->context->injectOpenAi($payload['messages'], $context);
        }

        $payload = $this->toolSchemas->normalizeOpenAi($payload);
        $payload['messages'] = $this->sanitizeOpenAiMessages($payload['messages']);

        $headers = [
            'X-Neiro-Memory-Hits' => (string) count($hits),
        ];

        if ($conversation !== null) {
            $headers['X-Neiro-Conversation'] = (string) $conversation->id;
        }

        if (($payload['stream'] ?? false) === true) {
            return $this->streamOpenAi($upstream, $payload, $hits, $conversation, $headers);
        }

        if ($upstream->mock) {
            $data = $this->mock->openAiCompletion($payload, $hits);
            $this->recordAssistant($conversation, (string) $data['choices'][0]['message']['content'], $data);

            return response()->json($data, 200, $headers);
        }

        $response = Http::withHeaders($this->openAiHeaders($upstream))
            ->withOptions(['http_errors' => false])
            ->timeout($upstream->timeout)
            ->post($upstream->url('chat/completions'), $payload);

        if ($response->failed()) {
            return response($response->body(), $response->status(), [
                'Content-Type' => $response->header('Content-Type') ?: 'application/json',
                ...$headers,
            ]);
        }

        $data = $response->json();

        if (is_array($data)) {
            $text = (string) ($data['choices'][0]['message']['content'] ?? '');

            if (is_array($data['choices'][0]['message']['content'] ?? null)) {
                $text = $this->contentBlocksToText($data['choices'][0]['message']['content']);
            }

            $this->recordAssistant($conversation, $text, $data);
        }

        return response($response->body(), $response->status(), [
            'Content-Type' => $response->header('Content-Type') ?: 'application/json',
            ...$headers,
        ]);
    }

    public function responses(Request $request): Response
    {
        $payload = $request->json()->all();

        if (! array_key_exists('input', $payload) && ! array_key_exists('messages', $payload)) {
            return $this->error('The "input" field is required.', 422, 'openai');
        }

        $upstream = $this->resolveUpstream($request, $payload, 'openai');
        $configurationError = $this->configurationError($upstream, 'openai');

        if ($configurationError !== null) {
            return $configurationError;
        }

        $lastUserText = $this->context->lastResponsesInputText($payload['input'] ?? $payload['messages'] ?? '');
        $conversation = $this->captureResponsesRequest($request, $payload, $lastUserText);
        $hits = $this->memoryHits($request, $lastUserText);
        $context = $this->context->buildHits($hits);

        if ($context !== '') {
            $payload['instructions'] = $this->context->injectInstructions(
                isset($payload['instructions']) ? (string) $payload['instructions'] : null,
                $context,
            );
        }

        $payload = $this->toolSchemas->normalizeOpenAi($payload);

        $headers = ['X-Neiro-Memory-Hits' => (string) count($hits)];

        if ($conversation !== null) {
            $headers['X-Neiro-Conversation'] = (string) $conversation->id;
        }

        if (($payload['stream'] ?? false) === true) {
            return $this->streamResponses($upstream, $payload, $hits, $conversation, $headers);
        }

        if ($upstream->mock) {
            $data = $this->mock->openAiResponse($payload, $hits);
            $this->recordAssistant($conversation, $this->responseOutputText($data), $data);

            return response()->json($data, 200, $headers);
        }

        $response = Http::withHeaders($this->openAiHeaders($upstream))
            ->withOptions(['http_errors' => false])
            ->timeout($upstream->timeout)
            ->post($upstream->url('responses'), $payload);

        if ($response->failed()) {
            return response($response->body(), $response->status(), [
                'Content-Type' => $response->header('Content-Type') ?: 'application/json',
                ...$headers,
            ]);
        }

        $data = $response->json();

        if (is_array($data)) {
            $this->recordAssistant($conversation, $this->responseOutputText($data), $data);
        }

        return response($response->body(), $response->status(), [
            'Content-Type' => $response->header('Content-Type') ?: 'application/json',
            ...$headers,
        ]);
    }

    public function messages(Request $request): Response
    {
        $payload = $request->json()->all();

        if (! is_array($payload['messages'] ?? null) || $payload['messages'] === []) {
            return $this->error('The "messages" field is required.', 422, 'anthropic');
        }

        $upstream = $this->resolveUpstream($request, $payload, 'anthropic');
        $configurationError = $this->configurationError($upstream, 'anthropic');

        if ($configurationError !== null) {
            return $configurationError;
        }

        $lastUserText = $this->context->lastAnthropicUserText($payload['messages']);
        $conversation = $this->captureRequest($request, $payload, $lastUserText);
        $hits = $this->memoryHits($request, $lastUserText);
        $context = $this->context->buildHits($hits);

        if ($context !== '') {
            $payload['system'] = $this->context->injectAnthropicSystem($payload['system'] ?? null, $context);
        }

        $payload = $this->toolSchemas->normalizeAnthropic($payload);
        $payload['messages'] = $this->sanitizeAnthropicMessages($payload['messages']);

        $headers = ['X-Neiro-Memory-Hits' => (string) count($hits)];

        if ($conversation !== null) {
            $headers['X-Neiro-Conversation'] = (string) $conversation->id;
        }

        if (($payload['stream'] ?? false) === true) {
            return $this->streamAnthropic($request, $upstream, $payload, $hits, $conversation, $headers);
        }

        if ($upstream->mock) {
            $data = $this->mock->anthropicMessage($payload, $hits);
            $this->recordAssistant($conversation, (string) $data['content'][0]['text'], $data);

            return response()->json($data, 200, $headers);
        }

        $response = Http::withHeaders($this->anthropicHeaders($request, $upstream))
            ->withOptions(['http_errors' => false])
            ->timeout($upstream->timeout)
            ->post($upstream->url('messages'), $payload);

        if ($response->failed()) {
            return response($response->body(), $response->status(), [
                'Content-Type' => $response->header('Content-Type') ?: 'application/json',
                ...$headers,
            ]);
        }

        $data = $response->json();

        if (is_array($data)) {
            $this->recordAssistant($conversation, $this->contentBlocksToText($data['content'] ?? []), $data);
        }

        return response($response->body(), $response->status(), [
            'Content-Type' => $response->header('Content-Type') ?: 'application/json',
            ...$headers,
        ]);
    }

    public function countTokens(Request $request): Response
    {
        $payload = $request->json()->all();
        $upstream = $this->resolveUpstream($request, $payload);

        if ($upstream->mock) {
            $text = json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '';

            return response()->json(['input_tokens' => max(1, (int) ceil(mb_strlen($text) / 3.2))]);
        }

        if ($upstream->baseUrl === '' || $upstream->apiKey === '') {
            return $this->error('Upstream API is not configured.', 503, 'anthropic');
        }

        $response = Http::withHeaders($this->anthropicHeaders($request, $upstream))
            ->withOptions(['http_errors' => false])
            ->timeout($upstream->timeout)
            ->post($upstream->url('messages/count_tokens'), $payload);

        return response($response->body(), $response->status(), [
            'Content-Type' => $response->header('Content-Type') ?: 'application/json',
        ]);
    }

    public function models(Request $request): Response
    {
        $upstream = $this->resolveUpstream($request, [], 'openai');

        if ($upstream->mock) {
            return response()->json([
                'object' => 'list',
                'data' => [[
                    'id' => $upstream->model,
                    'object' => 'model',
                    'created' => time(),
                    'owned_by' => 'neiro-brain',
                ]],
            ]);
        }

        $response = Http::withHeaders($upstream->isAnthropic()
            ? $this->anthropicHeaders($request, $upstream)
            : $this->openAiHeaders($upstream))
            ->withOptions(['http_errors' => false])
            ->timeout($upstream->timeout)
            ->get($upstream->url('models'));

        return response($response->body(), $response->status(), [
            'Content-Type' => $response->header('Content-Type') ?: 'application/json',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<SearchHit>  $hits
     * @param  array<string, string>  $headers
     */
    private function streamOpenAi(
        UpstreamConfig $upstream,
        array $payload,
        array $hits,
        ?Conversation $conversation,
        array $headers,
    ): Response {
        $streamHeaders = [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
            ...$headers,
        ];

        if ($upstream->mock) {
            return response()->stream(function () use ($payload, $hits, $conversation): void {
                $text = $this->mock->answer($payload, $hits);
                $this->emitOpenAiSse($text, (string) ($payload['model'] ?? 'neiro-mock'));
                $this->recordAssistant($conversation, $text, ['mock' => true]);
            }, 200, $streamHeaders);
        }

        $response = Http::withHeaders($this->openAiHeaders($upstream))
            ->withOptions(['stream' => true, 'http_errors' => false])
            ->timeout($upstream->timeout)
            ->post($upstream->url('chat/completions'), $payload);

        if ($response->failed()) {
            return response($response->body(), $response->status(), [
                'Content-Type' => $response->header('Content-Type') ?: 'application/json',
                ...$headers,
            ]);
        }

        return response()->stream(function () use ($response, $conversation): void {
            ignore_user_abort(true);
            $body = $response->toPsrResponse()->getBody();
            $buffer = '';
            $text = '';

            try {
                while (! $body->eof()) {
                    $chunk = $body->read(8192);

                    if ($chunk === '') {
                        usleep(5000);

                        continue;
                    }

                    echo $chunk;
                    @ob_flush();
                    flush();
                    $buffer .= $chunk;

                    while (($position = strpos($buffer, "\n\n")) !== false) {
                        $event = substr($buffer, 0, $position);
                        $buffer = substr($buffer, $position + 2);
                        $text .= $this->textFromOpenAiEvent($event);
                    }
                }

                if (trim($buffer) !== '') {
                    $text .= $this->textFromOpenAiEvent($buffer);
                }
            } finally {
                $this->recordAssistant($conversation, $text, ['stream' => true]);
            }
        }, $response->status(), $streamHeaders);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<SearchHit>  $hits
     * @param  array<string, string>  $headers
     */
    private function streamAnthropic(
        Request $request,
        UpstreamConfig $upstream,
        array $payload,
        array $hits,
        ?Conversation $conversation,
        array $headers,
    ): Response {
        $streamHeaders = [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
            ...$headers,
        ];

        if ($upstream->mock) {
            return response()->stream(function () use ($payload, $hits, $conversation): void {
                $text = $this->mock->answer($payload, $hits);
                $this->emitAnthropicSse($text, (string) ($payload['model'] ?? 'neiro-mock'));
                $this->recordAssistant($conversation, $text, ['mock' => true]);
            }, 200, $streamHeaders);
        }

        $response = Http::withHeaders($this->anthropicHeaders($request, $upstream))
            ->withOptions(['stream' => true, 'http_errors' => false])
            ->timeout($upstream->timeout)
            ->post($upstream->url('messages'), $payload);

        if ($response->failed()) {
            return response($response->body(), $response->status(), [
                'Content-Type' => $response->header('Content-Type') ?: 'application/json',
                ...$headers,
            ]);
        }

        return response()->stream(function () use ($response, $conversation): void {
            ignore_user_abort(true);
            $body = $response->toPsrResponse()->getBody();
            $buffer = '';
            $text = '';

            try {
                while (! $body->eof()) {
                    $chunk = $body->read(8192);

                    if ($chunk === '') {
                        usleep(5000);

                        continue;
                    }

                    echo $chunk;
                    @ob_flush();
                    flush();
                    $buffer .= $chunk;

                    while (($position = strpos($buffer, "\n\n")) !== false) {
                        $event = substr($buffer, 0, $position);
                        $buffer = substr($buffer, $position + 2);
                        $text .= $this->textFromAnthropicEvent($event);
                    }
                }

                if (trim($buffer) !== '') {
                    $text .= $this->textFromAnthropicEvent($buffer);
                }
            } finally {
                $this->recordAssistant($conversation, $text, ['stream' => true]);
            }
        }, $response->status(), $streamHeaders);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<SearchHit>  $hits
     * @param  array<string, string>  $headers
     */
    private function streamResponses(
        UpstreamConfig $upstream,
        array $payload,
        array $hits,
        ?Conversation $conversation,
        array $headers,
    ): Response {
        $streamHeaders = [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
            ...$headers,
        ];

        if ($upstream->mock) {
            return response()->stream(function () use ($payload, $hits, $conversation): void {
                $text = $this->mock->answer($payload, $hits);
                $this->emitResponsesSse($text, (string) ($payload['model'] ?? 'neiro-mock'));
                $this->recordAssistant($conversation, $text, ['mock' => true]);
            }, 200, $streamHeaders);
        }

        $response = Http::withHeaders($this->openAiHeaders($upstream))
            ->withOptions(['stream' => true, 'http_errors' => false])
            ->timeout($upstream->timeout)
            ->post($upstream->url('responses'), $payload);

        if ($response->failed()) {
            return response($response->body(), $response->status(), [
                'Content-Type' => $response->header('Content-Type') ?: 'application/json',
                ...$headers,
            ]);
        }

        return response()->stream(function () use ($response, $conversation): void {
            ignore_user_abort(true);
            $body = $response->toPsrResponse()->getBody();
            $buffer = '';
            $text = '';

            try {
                while (! $body->eof()) {
                    $chunk = $body->read(8192);

                    if ($chunk === '') {
                        usleep(5000);

                        continue;
                    }

                    echo $chunk;
                    @ob_flush();
                    flush();
                    $buffer .= $chunk;

                    while (($position = strpos($buffer, "\n\n")) !== false) {
                        $event = substr($buffer, 0, $position);
                        $buffer = substr($buffer, $position + 2);
                        $text .= $this->textFromResponsesEvent($event);
                    }
                }

                if (trim($buffer) !== '') {
                    $text .= $this->textFromResponsesEvent($buffer);
                }
            } finally {
                $this->recordAssistant($conversation, $text, ['stream' => true]);
            }
        }, $response->status(), $streamHeaders);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function captureResponsesRequest(Request $request, array $payload, string $lastUserText): ?Conversation
    {
        if (! $this->settings->enabled || ! $this->settings->capture) {
            return null;
        }

        $input = $payload['input'] ?? $payload['messages'] ?? [];
        $messages = [];

        if (is_string($input)) {
            $messages[] = ['role' => 'user', 'content' => $input];
        } elseif (is_array($input)) {
            foreach ($input as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $role = (string) ($item['role'] ?? '');

                if (! in_array($role, ['user', 'assistant'], true)) {
                    continue;
                }

                $content = is_string($item['content'] ?? null)
                    ? $item['content']
                    : $this->contentBlocksToText($item['content'] ?? $item['text'] ?? []);

                if (trim($content) !== '') {
                    $messages[] = ['role' => $role, 'content' => $content];
                }
            }
        }

        if ($messages === []) {
            return null;
        }

        $result = $this->ingestor->ingest(
            source: $this->sources->proxy(),
            externalId: $this->sessionId($request, $payload),
            messages: $messages,
            attributes: [
                'title' => Str::limit(trim($lastUserText), 120) ?: null,
                'harness' => $this->harness($request),
                'project' => $this->project($request, $payload),
                'metadata' => array_filter([
                    'model' => $payload['model'] ?? null,
                    'api' => 'responses',
                    'user_agent' => $request->userAgent(),
                ]),
            ],
        );

        return $result['conversation'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function responseOutputText(array $data): string
    {
        $parts = [];

        foreach ((array) ($data['output'] ?? []) as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($item['content'] ?? []) as $block) {
                if (is_array($block) && isset($block['text'])) {
                    $parts[] = (string) $block['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private function textFromResponsesEvent(string $event): string
    {
        $text = '';

        foreach (preg_split('/\r?\n/', $event) ?: [] as $line) {
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $data = trim(substr($line, 5));

            if ($data === '' || $data === '[DONE]') {
                continue;
            }

            $json = json_decode($data, true);

            if (! is_array($json)) {
                continue;
            }

            if (($json['type'] ?? null) === 'response.output_text.delta') {
                $text .= (string) ($json['delta'] ?? '');
            }
        }

        return $text;
    }

    private function emitResponsesSse(string $text, string $model): void
    {
        $id = 'resp_neiro_'.Str::random(16);

        echo 'event: response.created'."\n".'data: '.json_encode(['type' => 'response.created', 'response' => ['id' => $id, 'object' => 'response', 'status' => 'in_progress', 'model' => $model]], JSON_UNESCAPED_UNICODE)."\n\n";

        foreach (mb_str_split($text, 48) as $piece) {
            echo 'event: response.output_text.delta'."\n".'data: '.json_encode(['type' => 'response.output_text.delta', 'delta' => $piece], JSON_UNESCAPED_UNICODE)."\n\n";
            @ob_flush();
            flush();
        }

        echo 'event: response.completed'."\n".'data: '.json_encode(['type' => 'response.completed', 'response' => ['id' => $id, 'object' => 'response', 'status' => 'completed', 'model' => $model]], JSON_UNESCAPED_UNICODE)."\n\n";
        @ob_flush();
        flush();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function captureRequest(Request $request, array $payload, string $lastUserText): ?Conversation
    {
        if (! $this->settings->enabled || ! $this->settings->capture) {
            return null;
        }

        $messages = [];
        $rawMessages = is_array($payload['messages'] ?? null) ? $payload['messages'] : [];

        foreach ($rawMessages as $message) {
            $role = (string) ($message['role'] ?? '');

            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $content = is_string($message['content'] ?? null)
                ? $message['content']
                : $this->contentBlocksToText($message['content'] ?? []);

            if (trim($content) !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        if ($messages === []) {
            return null;
        }

        $source = $this->sources->proxy();
        $result = $this->ingestor->ingest(
            source: $source,
            externalId: $this->sessionId($request, $payload),
            messages: $messages,
            attributes: [
                'title' => Str::limit(trim($lastUserText), 120) ?: null,
                'harness' => $this->harness($request),
                'project' => $this->project($request, $payload),
                'metadata' => array_filter([
                    'model' => $payload['model'] ?? null,
                    'user_agent' => $request->userAgent(),
                ]),
            ],
        );

        return $result['conversation'];
    }

    /**
     * @param  list<SearchHit>  $hits
     */
    private function memoryHits(Request $request, string $text): array
    {
        if (! $this->settings->enabled || ! $this->settings->inject_memory || Str::lower((string) $request->header('X-Neiro-Memory')) === 'off') {
            return [];
        }

        $text = trim($text);

        if (mb_strlen($text) < 2) {
            return [];
        }

        $result = $this->search->search(new SearchQuery(
            query: Str::limit($text, 2000),
            limit: max(1, $this->settings->top_k),
        ), 'proxy');

        return $result->hits;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordAssistant(?Conversation $conversation, string $text, array $data): void
    {
        if ($conversation === null || trim($text) === '') {
            return;
        }

        try {
            $this->ingestor->append($conversation, [[
                'role' => 'assistant',
                'content' => $text,
                'occurred_at' => now(),
                'metadata' => array_filter([
                    'model' => $data['model'] ?? null,
                    'usage' => $data['usage'] ?? null,
                ]),
            ]]);
        } catch (\Throwable $exception) {
            Log::warning('Neiro Brain: failed to store proxied assistant message.', [
                'conversation_id' => $conversation->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Resolve the upstream from the harness, not from admin settings.
     *
     * The proxy is transparent: the key the client sends in Authorization or
     * x-api-key is the provider key and is forwarded upstream as is, so Neiro
     * Brain never stores it. If that key is Neiro Brain's own token (legacy
     * clients), it is not forwarded and the upstream comes from the settings.
     * X-Neiro-Upstream selects the provider; otherwise it is inferred from the
     * model name.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveUpstream(Request $request, array $payload, string $protocol = 'openai'): UpstreamConfig
    {
        $hasProxyToken = trim((string) $request->header('X-Neiro-Proxy-Token')) !== '';
        $provided = $request->bearerToken() ?: $request->header('x-api-key');
        $provided = is_string($provided) ? trim($provided) : '';

        $providerKey = null;

        if ($provided !== '' && $provided !== $this->brainToken()) {
            $providerKey = $provided;
        }

        $requestedUpstream = $request->header('X-Neiro-Upstream');
        $requestedUpstream = is_string($requestedUpstream) && trim($requestedUpstream) !== ''
            ? trim($requestedUpstream)
            : $this->inferUpstream($payload, $providerKey);

        return $this->resolver->resolve(
            $this->settings,
            isset($payload['model']) ? (string) $payload['model'] : null,
            $requestedUpstream,
            $providerKey,
            $protocol,
        );
    }

    /**
     * Strict OpenAI-compatible gateways reject a null message content, and the
     * harness sends null for assistant turns that carry no text (tool calls
     * only) or for an empty tool result. Normalizing it to an empty string
     * keeps the proxy transparent for the client without changing semantics.
     *
     * @param  array<int, mixed>  $messages
     * @return array<int, mixed>
     */
    private function sanitizeOpenAiMessages(array $messages): array
    {
        foreach ($messages as $index => $message) {
            if (! is_array($message)) {
                continue;
            }

            if (array_key_exists('content', $message) && $message['content'] === null) {
                $messages[$index]['content'] = '';
            }
        }

        return $messages;
    }

    /**
     * Strict gateways reject a null text value inside a content block, and the
     * harness may produce one when it replays an interrupted assistant turn.
     * Normalizing it here keeps the proxy transparent for the client.
     *
     * @param  array<int, mixed>  $messages
     * @return array<int, mixed>
     */
    private function sanitizeAnthropicMessages(array $messages): array
    {
        foreach ($messages as $index => $message) {
            if (! is_array($message) || ! is_array($message['content'] ?? null)) {
                continue;
            }

            foreach ($message['content'] as $blockIndex => $block) {
                if (! is_array($block)) {
                    continue;
                }

                if (($block['type'] ?? null) === 'text' && array_key_exists('text', $block) && $block['text'] === null) {
                    $messages[$index]['content'][$blockIndex]['text'] = '';
                }
            }
        }

        return $messages;
    }

    private function brainToken(): string
    {
        $unified = trim((string) config('brain.tokens.all', ''));

        return $unified !== '' ? $unified : trim((string) $this->settings->access_token);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function inferUpstream(array $payload, ?string $providerKey): ?string
    {
        if ($providerKey === null) {
            return null;
        }

        $model = strtolower((string) ($payload['model'] ?? ''));

        return match (true) {
            str_starts_with($model, 'deepseek') => 'deepseek',
            str_starts_with($model, 'claude') => 'anthropic',
            str_starts_with($model, 'gpt'),
            str_starts_with($model, 'o1'),
            str_starts_with($model, 'o3'),
            str_starts_with($model, 'o4') => 'openai',
            default => null,
        };
    }

    private function configurationError(UpstreamConfig $upstream, string $format): ?JsonResponse
    {
        if ($upstream->mock) {
            return null;
        }

        if ($upstream->baseUrl === '') {
            return $this->error('Upstream base URL is not configured.', 503, $format);
        }

        if ($upstream->apiKey === '' && $upstream->driver !== 'ollama') {
            return $this->error('Upstream API key is not configured.', 503, $format);
        }

        return null;
    }

    private function error(string $message, int $status, string $format): JsonResponse
    {
        if ($format === 'anthropic') {
            return response()->json([
                'type' => 'error',
                'error' => ['type' => 'proxy_error', 'message' => $message],
            ], $status);
        }

        return response()->json([
            'error' => [
                'message' => $message,
                'type' => 'neiro_proxy_error',
                'code' => 'upstream_unavailable',
            ],
        ], $status);
    }

    /**
     * @return array<string, string>
     */
    private function openAiHeaders(UpstreamConfig $upstream): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($upstream->apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$upstream->apiKey;
        }

        $headers['User-Agent'] = (string) config('brain.proxy.user_agent', 'NeiroBrain/1.0');

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function anthropicHeaders(Request $request, UpstreamConfig $upstream): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'x-api-key' => $upstream->apiKey,
            'anthropic-version' => (string) ($request->header('anthropic-version') ?: '2023-06-01'),
            'User-Agent' => (string) config('brain.proxy.user_agent', 'NeiroBrain/1.0'),
        ];

        $beta = $request->header('anthropic-beta');

        if (is_string($beta) && $beta !== '') {
            $headers['anthropic-beta'] = $beta;
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sessionId(Request $request, array $payload): string
    {
        $explicit = $request->header('X-Neiro-Session')
            ?: $request->header('X-Session-Id')
            ?: data_get($payload, 'metadata.session_id')
            ?: data_get($payload, 'metadata.user_id')
            ?: ($payload['user'] ?? null);

        if (is_string($explicit) && trim($explicit) !== '') {
            return 'proxy:'.mb_substr(preg_replace('/[^\p{L}\p{N}_.:-]+/u', '-', trim($explicit)) ?? 'session', 0, 180);
        }

        $system = $this->firstSystemMessage($payload);
        $fingerprint = implode('|', [
            (string) $request->userAgent(),
            $system,
            now()->toDateString(),
        ]);

        return 'proxy:'.substr(hash('sha256', $fingerprint), 0, 24);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function firstSystemMessage(array $payload): string
    {
        if (is_string($payload['system'] ?? null)) {
            return mb_substr($payload['system'], 0, 3000);
        }

        foreach ((array) ($payload['messages'] ?? []) as $message) {
            if (($message['role'] ?? null) === 'system' && is_string($message['content'] ?? null)) {
                return mb_substr($message['content'], 0, 3000);
            }
        }

        return '';
    }

    private function harness(Request $request): string
    {
        $explicit = $request->header('X-Neiro-Harness');

        if (is_string($explicit) && $explicit !== '') {
            return mb_substr($explicit, 0, 64);
        }

        $agent = Str::lower((string) $request->userAgent());

        return match (true) {
            str_contains($agent, 'claude-code') => 'claude-code',
            str_contains($agent, 'codex') => 'codex',
            str_contains($agent, 'cursor') => 'cursor',
            str_contains($agent, 'cline') => 'cline',
            str_contains($agent, 'continue') => 'continue',
            default => 'openai-compatible',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function project(Request $request, array $payload): ?string
    {
        $project = $request->header('X-Neiro-Project') ?: data_get($payload, 'metadata.project');

        return is_string($project) && trim($project) !== '' ? mb_substr(trim($project), 0, 128) : null;
    }

    private function contentBlocksToText(mixed $blocks): string
    {
        if (is_string($blocks)) {
            return $blocks;
        }

        if (! is_array($blocks)) {
            return '';
        }

        $parts = [];

        foreach ($blocks as $block) {
            if (is_string($block)) {
                $parts[] = $block;
            } elseif (is_array($block) && isset($block['text'])) {
                $parts[] = (string) $block['text'];
            }
        }

        return implode("\n", $parts);
    }

    private function textFromOpenAiEvent(string $event): string
    {
        $text = '';

        foreach (preg_split('/\r?\n/', $event) ?: [] as $line) {
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $data = trim(substr($line, 5));

            if ($data === '' || $data === '[DONE]') {
                continue;
            }

            $json = json_decode($data, true);

            if (! is_array($json)) {
                continue;
            }

            $delta = $json['choices'][0]['delta']['content'] ?? '';

            if (is_string($delta)) {
                $text .= $delta;
            }
        }

        return $text;
    }

    private function textFromAnthropicEvent(string $event): string
    {
        $text = '';

        foreach (preg_split('/\r?\n/', $event) ?: [] as $line) {
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $data = trim(substr($line, 5));

            if ($data === '') {
                continue;
            }

            $json = json_decode($data, true);

            if (is_array($json) && ($json['type'] ?? null) === 'content_block_delta') {
                $text .= (string) ($json['delta']['text'] ?? '');
            }
        }

        return $text;
    }

    private function emitOpenAiSse(string $text, string $model): void
    {
        $id = 'chatcmpl-neiro-'.Str::random(16);

        $this->emitSse(['id' => $id, 'object' => 'chat.completion.chunk', 'created' => time(), 'model' => $model, 'choices' => [['index' => 0, 'delta' => ['role' => 'assistant', 'content' => ''], 'finish_reason' => null]]]);

        foreach (mb_str_split($text, 48) as $piece) {
            $this->emitSse(['id' => $id, 'object' => 'chat.completion.chunk', 'created' => time(), 'model' => $model, 'choices' => [['index' => 0, 'delta' => ['content' => $piece], 'finish_reason' => null]]]);
        }

        $this->emitSse(['id' => $id, 'object' => 'chat.completion.chunk', 'created' => time(), 'model' => $model, 'choices' => [['index' => 0, 'delta' => new \stdClass, 'finish_reason' => 'stop']]]);

        echo "data: [DONE]\n\n";
        @ob_flush();
        flush();
    }

    private function emitAnthropicSse(string $text, string $model): void
    {
        $id = 'msg_neiro_'.Str::random(16);

        echo 'event: message_start'."\n".'data: '.json_encode(['type' => 'message_start', 'message' => ['id' => $id, 'type' => 'message', 'role' => 'assistant', 'model' => $model, 'content' => [], 'stop_reason' => null, 'usage' => ['input_tokens' => 1, 'output_tokens' => 0]]], JSON_UNESCAPED_UNICODE)."\n\n";
        echo 'event: content_block_start'."\n".'data: '.json_encode(['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']], JSON_UNESCAPED_UNICODE)."\n\n";

        foreach (mb_str_split($text, 48) as $piece) {
            echo 'event: content_block_delta'."\n".'data: '.json_encode(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => $piece]], JSON_UNESCAPED_UNICODE)."\n\n";
            @ob_flush();
            flush();
        }

        echo 'event: content_block_stop'."\n".'data: '.json_encode(['type' => 'content_block_stop', 'index' => 0], JSON_UNESCAPED_UNICODE)."\n\n";
        echo 'event: message_stop'."\n".'data: '.json_encode(['type' => 'message_stop'], JSON_UNESCAPED_UNICODE)."\n\n";
        @ob_flush();
        flush();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function emitSse(array $data): void
    {
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
        @ob_flush();
        flush();
    }
}
