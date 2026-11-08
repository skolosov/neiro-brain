<?php

declare(strict_types=1);

namespace App\Services\Proxy;

use App\Services\Brain\Dto\SearchHit;
use Illuminate\Support\Str;

/**
 * Offline upstream used for demos and tests.
 *
 * It answers in the shape of the requested protocol and quotes the memory that
 * the proxy injected, so the whole pipeline is observable without API keys.
 */
final class MockUpstream
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<SearchHit>  $hits
     * @return array<string, mixed>
     */
    public function openAiCompletion(array $payload, array $hits): array
    {
        $text = $this->answer($payload, $hits);
        $model = (string) ($payload['model'] ?? 'neiro-mock');
        $promptTokens = $this->estimateTokens($payload);

        return [
            'id' => 'chatcmpl-neiro-'.Str::random(16),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $model,
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $text],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $this->estimateTextTokens($text),
                'total_tokens' => $promptTokens + $this->estimateTextTokens($text),
            ],
            'neiro' => ['memory_hits' => count($hits), 'mock' => true],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<SearchHit>  $hits
     * @return array<string, mixed>
     */
    public function anthropicMessage(array $payload, array $hits): array
    {
        $text = $this->answer($payload, $hits);
        $promptTokens = $this->estimateTokens($payload);

        return [
            'id' => 'msg_neiro_'.Str::random(16),
            'type' => 'message',
            'role' => 'assistant',
            'model' => (string) ($payload['model'] ?? 'neiro-mock'),
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
            'usage' => [
                'input_tokens' => $promptTokens,
                'output_tokens' => $this->estimateTextTokens($text),
            ],
            'neiro' => ['memory_hits' => count($hits), 'mock' => true],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<SearchHit>  $hits
     */
    public function answer(array $payload, array $hits): string
    {
        $question = $this->lastUserQuestion($payload);

        $lines = [
            'Это демонстрационный ответ Neiro Brain (upstream = mock).',
            '',
            $question !== '' ? 'Запрос: '.$question : 'Запрос не распознан.',
            '',
        ];

        if ($hits === []) {
            $lines[] = 'В базе знаний пока нет подходящих записей по этому запросу.';
            $lines[] = 'Подключите реального провайдера в настройках прокси, чтобы получать ответы модели.';
        } else {
            $lines[] = 'Что я помню по теме:';
            $lines[] = '';

            foreach ($hits as $hit) {
                $lines[] = sprintf(
                    '- [%s] %s: %s',
                    $hit->entry->kind->label(),
                    $hit->entry->title,
                    Str::limit(trim($hit->entry->content), 220),
                );
            }

            $lines[] = '';
            $lines[] = 'Память была подмешана в системный промпт автоматически — приложение-клиент этого не заметило.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function lastUserQuestion(array $payload): string
    {
        $messages = $payload['messages'] ?? [];

        if (! is_array($messages)) {
            return '';
        }

        foreach (array_reverse($messages) as $message) {
            if (($message['role'] ?? null) !== 'user') {
                continue;
            }

            $content = $message['content'] ?? '';

            if (is_string($content)) {
                return trim($content);
            }

            if (is_array($content)) {
                foreach ($content as $block) {
                    if (is_array($block) && isset($block['text'])) {
                        return trim((string) $block['text']);
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function estimateTokens(array $payload): int
    {
        return max(1, (int) ceil(mb_strlen(json_encode($payload['messages'] ?? [], JSON_UNESCAPED_UNICODE)) / 3.2));
    }

    private function estimateTextTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 3.2));
    }
}
