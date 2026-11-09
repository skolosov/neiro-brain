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
            $kind = $this->classify($sentence);

            // A card states a fact, it does not echo the user. Writing the
            // sentence in third person is what separates a card from a copied
            // line — and the quality gate rejects copied lines on purpose.
            $content = $this->toThirdPerson($sentence);
            $title = $this->title($content);

            if ($title === '' || $title === rtrim($content, ' .!?…')) {
                continue;
            }

            $entries[] = new ExtractedEntry(
                kind: $kind,
                title: $title,
                content: $content,
                details: null,
                project: $conversation->project,
                tags: $this->tags($sentence),
                importance: $this->importance($kind),
                confidence: 0.55,
            );
        }

        return $entries;
    }

    /**
     * Rewrite a first-person sentence as a statement about the user.
     *
     * Only the opening is transformed: the rest of the sentence keeps the
     * user's own words, so nothing is invented.
     */
    private function toThirdPerson(string $sentence): string
    {
        $map = [
            'я не люблю' => 'Пользователь не любит',
            'я не использую' => 'Пользователь не использует',
            'я предпочитаю' => 'Пользователь предпочитает',
            'я использую' => 'Пользователь использует',
            'я выбираю' => 'Пользователь выбирает',
            'я планирую' => 'Пользователь планирует',
            'я работаю' => 'Пользователь работает',
            'я пишу' => 'Пользователь пишет',
            'я хочу' => 'Пользователь хочет',
            'я люблю' => 'Пользователь любит',
            'мне не нравится' => 'Пользователю не нравится',
            'мне нравится' => 'Пользователю нравится',
            'мы не используем' => 'Команда не использует',
            'мы используем' => 'Команда использует',
            'мы решили' => 'Команда решила',
            'мы выбрали' => 'Команда выбрала',
            'мы отказались' => 'Команда отказалась',
            'мой ' => 'Его ',
            'моя ' => 'Его ',
            'мои ' => 'Его ',
            'наш ' => 'Их ',
            'наша ' => 'Их ',
        ];

        $lower = mb_strtolower($sentence);

        foreach ($map as $from => $to) {
            if (str_starts_with($lower, $from)) {
                return $to.mb_substr($sentence, mb_strlen($from));
            }
        }

        return $sentence;
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

            // Transcript artefacts: bullet lists, headings, key-value notes and
            // anything already truncated by an earlier summarizer.
            if (preg_match('/^[-–—*•#>|]/u', $part) === 1) {
                continue;
            }

            if (preg_match('/^[\p{L} .]{2,20}:\s/u', $part) === 1) {
                continue;
            }

            if (str_contains($part, '…') || str_contains($part, '...')) {
                continue;
            }

            if (preg_match('/^[A-Za-z .]+$/u', $part) === 1 && ! preg_match('/[А-Яа-яЁё]/u', $part)) {
                continue;
            }

            $sentences[] = $part;
        }

        return array_values(array_unique($sentences));
    }

    /**
     * A sentence becomes a card only when it clearly speaks about the user's
     * own setup. Everything else is transcript noise: task instructions,
     * pasted context, tool output and English boilerplate from harnesses.
     */
    private function score(string $sentence, Message $message): float
    {
        $lower = mb_strtolower($sentence);
        $score = 0.0;

        if ($message->role === 'user') {
            $score += 0.5;
        }

        $selfReference = preg_match('/(^|\s)(я|мы|мой|моя|мои|моё|наш|наша|наши|мне|меня|нас)\s/u', $lower) === 1;
        $preference = preg_match('/люблю|предпочитаю|нравится|не люблю|бесит|привык|удобн|использую|работаю|пишу|выбираю|хочу|планирую/iu', $lower) === 1;
        $decision = preg_match('/решили|выбрали|договорились|приняли|отказались|пришли к|остановились на/iu', $lower) === 1;
        $team = preg_match('/компани|команда|клиент|заказчик|продукт|проект/iu', $lower) === 1;

        // No self-reference and no decision — this is not knowledge about the
        // user, whatever else it contains.
        if (! $selfReference && ! $decision && ! $preference) {
            return 0.0;
        }

        $score += $selfReference ? 1.2 : 0.0;
        $score += $preference ? 1.0 : 0.0;
        $score += $decision ? 1.0 : 0.0;
        $score += $team ? 0.3 : 0.0;

        if (preg_match('/\b(laravel|php|postgres|postgresql|docker|vue|react|mcp|bm25|llm|moonshine|redis|nginx|1с)\b/u', $lower) === 1) {
            $score += 0.6;
        }

        if (str_ends_with($sentence, '?')) {
            $score -= 1.4;
        }

        if (preg_match('/^(конечно|вот|пример|давай|итак|например|смотри|сделай|добавь|покажи|напиши|проверь)\b/u', $lower) === 1) {
            $score -= 1.6;
        }

        if (preg_match('/^(current|this snapshot|note:|todo|file|path|config|<|#)/u', $lower) === 1) {
            return 0.0;
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
        $clean = trim(preg_replace('/^[\s\-–—*•\d.)"«]+/u', '', $sentence) ?? $sentence);

        // A title is a theme, not half a sentence: cut at punctuation or dash
        // instead of truncating mid-word, and keep it short enough to scan.
        $parts = preg_split('/[,:;—–]| - /u', $clean) ?: [$clean];
        $title = rtrim(trim($parts[0]), ' .!?…');

        if (mb_strlen($title) > 46) {
            $head = mb_substr($title, 0, 46);
            $space = mb_strrpos($head, ' ');
            $title = rtrim($space !== false ? mb_substr($head, 0, $space) : $head, ' .!?…,:;—–-');
        }

        return mb_strtoupper(mb_substr($title, 0, 1)).mb_substr($title, 1);
    }

    private function fingerprint(string $sentence): string
    {
        $normalized = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $sentence) ?? $sentence);
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);

        return hash('sha256', $normalized);
    }
}
