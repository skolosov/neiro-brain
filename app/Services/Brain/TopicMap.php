<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Models\KnowledgeEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Topics are the middle layer between projects and single cards.
 *
 * An entry lands in a topic through its tags; when an entry has no tags the
 * topic is derived from its kind (решения, стек, стиль работы…), so every card
 * belongs to at least one theme and the graph never shows loose dots.
 */
final class TopicMap
{
    /** Normalized synonyms for the most common tags. */
    private const ALIASES = [
        'laravel' => 'Laravel',
        'php' => 'PHP',
        'postgres' => 'PostgreSQL',
        'postgresql' => 'PostgreSQL',
        'pgsql' => 'PostgreSQL',
        'redis' => 'Redis',
        'docker' => 'Docker',
        'vue' => 'Vue',
        'vue3' => 'Vue',
        'mcp' => 'MCP',
        'bm25' => 'Поиск',
        'search' => 'Поиск',
        'поиск' => 'Поиск',
        'архитектура' => 'Архитектура',
        'architecture' => 'Архитектура',
        'тесты' => 'Тесты',
        'тестирование' => 'Тесты',
        'tests' => 'Тесты',
        'деплой' => 'Деплой',
        'deploy' => 'Деплой',
        'ci' => 'Деплой',
        'openai' => 'LLM',
        'deepseek' => 'LLM',
        'llm' => 'LLM',
        'claude' => 'LLM',
        'процессы' => 'Процессы',
        'process' => 'Процессы',
        'preferences' => 'Предпочтения',
        'preference' => 'Предпочтения',
        'profile' => 'Профиль',
    ];

    /**
     * Topics of one entry.
     *
     * @return list<string>
     */
    public function of(KnowledgeEntry $entry): array
    {
        $topics = [];

        foreach ($entry->tags ?? [] as $tag) {
            $topic = $this->normalize((string) $tag);

            if ($topic !== '') {
                $topics[$topic] = true;
            }
        }

        if ($topics === []) {
            $topics[$this->kindTopic($entry)] = true;
        }

        return array_keys($topics);
    }

    /**
     * Topics for a whole set of entries: topic => entries.
     *
     * @param  iterable<KnowledgeEntry>  $entries
     * @return Collection<string, Collection<int, KnowledgeEntry>>
     */
    public function group(iterable $entries): Collection
    {
        $grouped = [];

        foreach ($entries as $entry) {
            foreach ($this->of($entry) as $topic) {
                $grouped[$topic][] = $entry;
            }
        }

        ksort($grouped);

        return collect($grouped)->map(fn (array $items): Collection => collect($items));
    }

    /**
     * Topic cloud with counts, biggest first.
     *
     * @return Collection<string, int>
     */
    public function counts(int $limit = 40, bool $approvedOnly = true): Collection
    {
        $entries = KnowledgeEntry::query()
            ->when($approvedOnly, fn ($query) => $query->approved())
            ->get(['id', 'kind', 'tags']);

        $counts = [];

        foreach ($this->group($entries) as $topic => $items) {
            $counts[$topic] = $items->count();
        }

        arsort($counts);

        return collect(array_slice($counts, 0, $limit, true));
    }

    /**
     * Entries of one topic.
     *
     * Filtering happens in PHP because tags are stored as the user wrote them
     * ("Интеграции", "интеграции") while topics are normalized — a JSON query
     * would be case-sensitive and silently miss half the cards.
     *
     * @return Collection<int, KnowledgeEntry>
     */
    public function entries(string $topic, int $limit = 60): Collection
    {
        $topic = $this->normalize($topic);

        return KnowledgeEntry::query()
            ->approved()
            ->orderByDesc('importance')
            ->limit(max($limit * 4, 200))
            ->get()
            ->filter(fn (KnowledgeEntry $entry): bool => in_array($topic, $this->of($entry), true))
            ->take($limit)
            ->values();
    }

    public function normalize(string $tag): string
    {
        $tag = trim($tag);

        if ($tag === '') {
            return '';
        }

        $key = mb_strtolower(preg_replace('/[^\p{L}\p{N}+#]+/u', '', $tag) ?? $tag);

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        return Str::ucfirst($tag);
    }

    private function kindTopic(KnowledgeEntry $entry): string
    {
        return match ($entry->kind?->value) {
            'decision' => 'Решения',
            'stack' => 'Стек',
            'style' => 'Стиль работы',
            'process' => 'Процессы',
            'preference' => 'Предпочтения',
            'profile', 'person' => 'Профиль',
            'company' => 'Компания',
            'project' => 'Проекты',
            default => 'Факты',
        };
    }
}
