<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Models\KnowledgeEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Topics are the middle layer between projects and single cards.
 *
 * The extractor writes free-form tags ("монолит", "визуализация", "retention"),
 * which is great for search and useless for navigation: a hundred cards would
 * produce a hundred one-off labels. This class maps them onto a small canonical
 * set, so the graph shows a dozen real themes instead of a star of tags.
 */
final class TopicMap
{
    /** Canonical topic per normalized tag. */
    private const ALIASES = [
        // Стек и технологии
        'laravel' => 'Стек',
        'php' => 'Стек',
        'postgres' => 'Данные',
        'postgresql' => 'Данные',
        'pgsql' => 'Данные',
        'redis' => 'Стек',
        'docker' => 'Стек',
        'vue' => 'Стек',
        'vue3' => 'Стек',
        'стек' => 'Стек',
        'опенсервер' => 'Стек',
        'openserver' => 'Стек',
        'ospanel' => 'Стек',
        'окружение' => 'Стек',
        'windows' => 'Стек',
        'linux' => 'Стек',

        // Архитектура
        'архитектура' => 'Архитектура',
        'architecture' => 'Архитектура',
        'микросервисы' => 'Архитектура',
        'микросервис' => 'Архитектура',
        'монолит' => 'Архитектура',
        'design' => 'Архитектура',
        'frontend' => 'Архитектура',
        'backend' => 'Архитектура',

        // Поиск
        'bm25' => 'Поиск',
        'search' => 'Поиск',
        'поиск' => 'Поиск',
        'индекс' => 'Поиск',
        'индексация' => 'Поиск',

        // Данные и приватность
        'хранение' => 'Данные',
        'хранение данных' => 'Данные',
        'retention' => 'Данные',
        'ретеншн' => 'Данные',
        'приватность' => 'Данные',
        'privacy' => 'Данные',
        'бд' => 'Данные',
        'миграции' => 'Данные',

        // Карточки и интерфейс
        'знания' => 'Карточки',
        'карточки' => 'Карточки',
        'карточка' => 'Карточки',
        'темы' => 'Карточки',
        'тема' => 'Карточки',
        'граф' => 'Карточки',
        'graph' => 'Карточки',
        'визуализация' => 'Карточки',
        'canvas' => 'Карточки',
        'интерфейс' => 'Карточки',
        'ui' => 'Карточки',
        'ux' => 'Карточки',
        'база знаний' => 'Карточки',

        // Админка и настройки
        'админка' => 'Админка',
        'moonshine' => 'Админка',
        'настройки' => 'Админка',
        'settings' => 'Админка',

        // Процессы
        'cron' => 'Процессы',
        'синхронизация' => 'Процессы',
        'суммаризация' => 'Процессы',
        'процессы' => 'Процессы',
        'process' => 'Процессы',
        'качество' => 'Процессы',
        'качество данных' => 'Процессы',
        'коммуникация' => 'Процессы',

        // Прокси и интеграции
        'прокси' => 'Прокси',
        'proxy' => 'Прокси',
        'api' => 'Прокси',
        'харнессы' => 'Прокси',
        'харнесс' => 'Прокси',
        'mcp' => 'Прокси',
        'интеграции' => 'Прокси',
        'интеграция' => 'Прокси',
        'идемпотентность' => 'Прокси',

        // LLM
        'llm' => 'LLM',
        'openai' => 'LLM',
        'deepseek' => 'LLM',
        'claude' => 'LLM',
        'модель' => 'LLM',
        'ai' => 'LLM',

        // Стиль и тесты
        'стиль' => 'Стиль',
        'стиль работы' => 'Стиль',
        'тесты' => 'Стиль',
        'тестирование' => 'Стиль',
        'tests' => 'Стиль',
        'pest' => 'Стиль',
        'phpunit' => 'Стиль',
        'код' => 'Стиль',
        'конвенции' => 'Стиль',

        // Люди и компания
        'команда' => 'Команда',
        'компания' => 'Команда',
        'профиль' => 'Команда',
        'profile' => 'Команда',
        'предпочтения' => 'Предпочтения',
        'preference' => 'Предпочтения',
        'предпочтение' => 'Предпочтения',
        '1с' => 'Интеграции',
        '1c' => 'Интеграции',
        'bitrix' => 'Интеграции',
        'проект' => 'Проекты',
        'проекты' => 'Проекты',
    ];

    /** Substring rules for tags the extractor invents on the fly. */
    private const CONTAINS = [
        'архитектур' => 'Архитектура',
        'микросервис' => 'Архитектура',
        'монолит' => 'Архитектура',
        'поиск' => 'Поиск',
        'bm25' => 'Поиск',
        'индекс' => 'Поиск',
        'хранен' => 'Данные',
        'приватн' => 'Данные',
        'карточ' => 'Карточки',
        'граф' => 'Карточки',
        'визуал' => 'Карточки',
        'интерфейс' => 'Карточки',
        'знани' => 'Карточки',
        'админ' => 'Админка',
        'настройк' => 'Админка',
        'синхрон' => 'Процессы',
        'суммариз' => 'Процессы',
        'качеств' => 'Процессы',
        'прокси' => 'Прокси',
        'харнесс' => 'Прокси',
        'интеграц' => 'Прокси',
        'стил' => 'Стиль',
        'тест' => 'Стиль',
        'стек' => 'Стек',
        'окружен' => 'Стек',
        'команд' => 'Команда',
        'компани' => 'Команда',
        'проект' => 'Проекты',
        'lld' => 'LLM',
        'llm' => 'LLM',
        'модел' => 'LLM',
    ];

    /**
     * Topics of one entry, most significant first.
     *
     * Two themes per card is the sweet spot: the card stays findable through its
     * full tag list, but the graph does not explode into dozens of labels.
     *
     * @return list<string>
     */
    public function of(KnowledgeEntry $entry): array
    {
        $topics = [];
        $projectKey = $this->key((string) $entry->project);

        // The card's own theme wins: it is the one label the author chose,
        // whereas tags are search aids and their order carries no meaning.
        if ($entry->theme !== null && trim((string) $entry->theme) !== '') {
            $theme = $this->normalize((string) $entry->theme);

            if ($theme !== '' && ! ($projectKey !== '' && $this->key($theme) === $projectKey)) {
                return [$theme];
            }
        }

        foreach ($entry->tags ?? [] as $position => $tag) {
            // A tag repeating the project name is not a theme: the project layer
            // of the graph already shows it, so it would duplicate the hub.
            if ($projectKey !== '' && $this->key((string) $tag) === $projectKey) {
                continue;
            }

            $topic = $this->normalize((string) $tag);

            if ($topic === '') {
                continue;
            }

            $topics[$topic] ??= [
                'priority' => $this->priority($topic),
                'position' => $position,
            ];
        }

        if ($topics === []) {
            return [$this->kindTopic($entry)];
        }

        uasort($topics, fn (array $left, array $right): int => [$right['priority'], $left['position']] <=> [$left['priority'], $right['position']]);

        return array_slice(array_keys($topics), 0, 2);
    }

    private function key(string $value): string
    {
        return mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? $value);
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
    public function counts(int $limit = 40, bool $approvedOnly = true, int $min = 1): Collection
    {
        $entries = KnowledgeEntry::query()
            ->when($approvedOnly, fn ($query) => $query->approved())
            ->get(['id', 'kind', 'tags', 'theme', 'project']);

        $counts = [];

        foreach ($this->group($entries) as $topic => $items) {
            if ($items->count() >= $min) {
                $counts[$topic] = $items->count();
            }
        }

        arsort($counts);

        return collect(array_slice($counts, 0, $limit, true));
    }

    /**
     * Entries of one topic.
     *
     * Filtering happens in PHP because tags are stored as the user wrote them
     * and topics are canonical — a JSON query would miss most of the cards.
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

    /**
     * Map a free-form tag onto a canonical topic.
     */
    public function normalize(string $tag): string
    {
        $tag = trim($tag);

        if ($tag === '') {
            return '';
        }

        $key = mb_strtolower(preg_replace('/[^\p{L}\p{N}+#]+/u', ' ', $tag) ?? $tag);
        $key = trim(preg_replace('/\s+/u', ' ', $key) ?? $key);

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        foreach (self::CONTAINS as $needle => $topic) {
            if (str_contains($key, $needle)) {
                return $topic;
            }
        }

        return Str::ucfirst($tag);
    }

    private function priority(string $topic): int
    {
        return match ($topic) {
            'Архитектура', 'Процессы', 'Предпочтения', 'Команда', 'Карточки' => 5,
            'Поиск', 'Прокси', 'Админка', 'Данные', 'Стиль', 'LLM' => 4,
            default => 3,
        };
    }

    private function kindTopic(KnowledgeEntry $entry): string
    {
        return match ($entry->kind?->value) {
            'decision' => 'Архитектура',
            'stack' => 'Стек',
            'style' => 'Стиль',
            'process' => 'Процессы',
            'preference' => 'Предпочтения',
            'profile', 'person' => 'Команда',
            'company' => 'Команда',
            'project' => 'Проекты',
            default => 'Карточки',
        };
    }
}
