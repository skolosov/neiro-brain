<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Enums\KnowledgeKind;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Brain\Dto\ExtractedEntry;

/**
 * Deterministic extractor used when no AI provider is configured (and as a
 * safety net when the provider fails). It is intentionally conservative: a
 * handful of high-signal sentences beats a noisy wall of text.
 */
final class HeuristicExtractor
{
    /** @var array<string, list<string>> */
    private const TAG_KEYWORDS = [
        'laravel' => ['laravel', 'ларavel', 'ларавел'],
        'php' => ['php', 'пхп'],
        'postgres' => ['postgres', 'postgresql', 'постгрес'],
        'mysql' => ['mysql', 'майскл'],
        'vue' => ['vue', 'вью'],
        'react' => ['react', 'реакт'],
        'docker' => ['docker', 'докер'],
        'kubernetes' => ['kubernetes', 'k8s', 'кубер'],
        'moonshine' => ['moonshine', 'муншайн'],
        'mcp' => ['mcp'],
        'bm25' => ['bm25'],
        'ai' => ['llm', 'ai', 'ии', 'нейросет', 'модель'],
        'architecture' => ['архитектур', 'ddd', 'solid', 'cqs', 'микросервис'],
        'testing' => ['тест', 'pest', 'phpunit'],
        'devops' => ['ci/cd', 'деплой', 'docker', 'сервер', 'nginx'],
        '1c' => ['1с', '1c'],
        'bitrix' => ['bitrix', 'битрикс'],
    ];

    /**
     * @return list<ExtractedEntry>
     */
    public function extract(Conversation $conversation): array
    {
        $candidates = [];

        foreach ($conversation->messages as $message) {
            // Only the user's own words become long-term knowledge. Assistant
            // replies often paraphrase the prompt and add noise.
            if ($message->role !== 'user') {
                continue;
            }

            foreach ($this->sentences($message->content) as $sentence) {
                $score = $this->score($sentence, $message);

                if ($score <= 0) {
                    continue;
                }

                $key = $this->fingerprint($sentence);

                if (isset($candidates[$key]) && $candidates[$key]['score'] >= $score) {
                    continue;
                }

                $candidates[$key] = [
                    'sentence' => $sentence,
                    'score' => $score,
                    'role' => $message->role,
                ];
            }
        }

        uasort($candidates, fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $candidates = array_slice($candidates, 0, 12, true);
        $entries = [];

        foreach ($candidates as $candidate) {
            $sentence = $candidate['sentence'];
            $entries[] = new ExtractedEntry(
                kind: $this->classify($sentence),
                title: $this->title($sentence),
                content: $sentence,
                details: null,
                project: $conversation->project,
                tags: $this->tags($sentence),
                importance: $this->importance($this->classify($sentence)),
                confidence: 0.55,
            );
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function sentences(string $content): array
    {
        $content = preg_replace('/```.*?```/s', ' ', $content) ?? $content;
        $content = preg_replace('/`[^`]+`/u', ' ', $content) ?? $content;
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $parts = preg_split('/(?<=[.!?…])\s+|\n+/u', $content) ?: [];
        $sentences = [];

        foreach ($parts as $part) {
            $part = trim(preg_replace('/\s+/u', ' ', $part) ?? $part);

            if ($part === '' || mb_strlen($part) < 30 || mb_strlen($part) > 700) {
                continue;
            }

            if (preg_match('/https?:\/\//u', $part) === 1) {
                continue;
            }

            if (preg_match('/[{}<>]{2,}|=>|<\?php|\bcomposer\b|\bnpm\b/u', $part) === 1) {
                continue;
            }

            $sentences[] = $part;
        }

        return array_values(array_unique($sentences));
    }

    private function score(string $sentence, Message $message): float
    {
        $lower = mb_strtolower($sentence);
        $score = 1.0;

        if ($message->role === 'user') {
            $score += 0.8;
        }

        if (preg_match('/(^|\s)(я|мы|мой|моя|мои|наш|наша|наши|мне|меня)\s/u', $lower) === 1) {
            $score += 1.2;
        }

        foreach ([
            'люблю', 'предпочитаю', 'нравится', 'не люблю', 'бесит', 'привык',
            'использую', 'работаю', 'пишу', 'выбираю', 'хочу', 'планирую',
            'решили', 'выбрали', 'договорились', 'приняли', 'отказались',
            'проект', 'компания', 'клиент', 'команда', 'продукт', 'сервис',
        ] as $marker) {
            if (str_contains($lower, $marker)) {
                $score += 0.55;
            }
        }

        if (preg_match('/\b(laravel|php|postgres|postgresql|docker|vue|react|mcp|bm25|llm|moonshine|redis|nginx|1с)\b/u', $lower) === 1) {
            $score += 0.7;
        }

        if (str_ends_with($sentence, '?')) {
            $score -= 1.4;
        }

        if (preg_match('/^(конечно|вот|пример|давай|итак|например|смотри)\b/u', $lower) === 1) {
            $score -= 1.2;
        }

        return round($score, 2);
    }

    private function classify(string $sentence): KnowledgeKind
    {
        $lower = mb_strtolower($sentence);

        if (preg_match('/люблю|предпочитаю|нравится|не люблю|бесит|привык|удобн/iu', $lower) === 1) {
            return KnowledgeKind::Preference;
        }

        if (preg_match('/использую|стек|laravel|postgres|docker|vue|react|mcp|nginx|redis/iu', $lower) === 1) {
            return KnowledgeKind::Stack;
        }

        if (preg_match('/решили|выбрали|договорились|архитектур|подход|отказались|приняли/iu', $lower) === 1) {
            return KnowledgeKind::Decision;
        }

        if (preg_match('/компания|клиент|заказчик|команда|бизнес|сотрудник/iu', $lower) === 1) {
            return KnowledgeKind::Company;
        }

        if (preg_match('/проект|сервис|продукт|приложение|система/iu', $lower) === 1) {
            return KnowledgeKind::Project;
        }

        if (preg_match('/стиль|код|рефактор|тест|чист|нейминг/iu', $lower) === 1) {
            return KnowledgeKind::Style;
        }

        if (preg_match('/процесс|деплой|релиз|ci\/cd|спринт|задач/iu', $lower) === 1) {
            return KnowledgeKind::Process;
        }

        if (preg_match('/(меня зовут|мой опыт|я работаю|я занимаюсь|моя роль)/iu', $lower) === 1) {
            return KnowledgeKind::Profile;
        }

        return KnowledgeKind::Fact;
    }

    private function importance(KnowledgeKind $kind): int
    {
        return match ($kind) {
            KnowledgeKind::Profile, KnowledgeKind::Company => 5,
            KnowledgeKind::Preference, KnowledgeKind::Decision, KnowledgeKind::Style => 4,
            KnowledgeKind::Project, KnowledgeKind::Stack, KnowledgeKind::Process => 3,
            default => 2,
        };
    }

    /**
     * @return list<string>
     */
    private function tags(string $sentence): array
    {
        $lower = mb_strtolower($sentence);
        $tags = [];

        foreach (self::TAG_KEYWORDS as $tag => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $tags[] = $tag;

                    break;
                }
            }
        }

        return array_values(array_unique($tags));
    }

    private function title(string $sentence): string
    {
        $title = preg_replace('/^(я|мы|мне|меня|у меня|у нас)\s+/iu', '', $sentence) ?? $sentence;
        $title = rtrim($title, ' .!?…');
        $title = Text::limit($title, 90);

        return mb_strtoupper(mb_substr($title, 0, 1)).mb_substr($title, 1);
    }

    private function fingerprint(string $sentence): string
    {
        $normalized = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $sentence) ?? $sentence);
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);

        return hash('sha256', $normalized);
    }
}
