<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\KnowledgeSearch;
use App\Services\Brain\Text;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('search_knowledge')]
#[Description('Поиск по личной базе знаний пользователя (BM25). Используй перед ответом на вопросы о пользователе, его компании, проектах, стеке и предпочтениях.')]
class SearchKnowledgeTool extends Tool
{
    public function __construct(
        private readonly KnowledgeSearch $search,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:2000'],
            'project' => ['nullable', 'string', 'max:128'],
            'kinds' => ['nullable', 'array'],
            'kinds.*' => ['string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->search->search(new SearchQuery(
            query: $validated['query'],
            project: $validated['project'] ?? null,
            kinds: $validated['kinds'] ?? [],
            limit: $validated['limit'] ?? 8,
        ), 'mcp');

        if ($result->isEmpty()) {
            return Response::text('Ничего не найдено. Попробуй переформулировать запрос или не указывать проект.');
        }

        $lines = [
            sprintf('Найдено %d записей за %d мс.', count($result->hits), $result->tookMs),
            '',
        ];

        foreach ($result->hits as $hit) {
            $entry = $hit->entry;
            $lines[] = sprintf(
                "### [%d] %s\n%s\nТип: %s | Проект: %s | Важность: %d | Релевантность: %.3f\nТеги: %s",
                $entry->id,
                $entry->title,
                $entry->content,
                $entry->kind->label(),
                $entry->project ?? '—',
                $entry->importance,
                $hit->score,
                implode(', ', $entry->tags ?? []) ?: '—',
            );
            $lines[] = '';
        }

        $text = Text::limit(implode("\n", $lines), 12000);

        return Response::make(Response::text($text))->withStructuredContent([
            'query' => $result->query,
            'hits' => $result->toArray(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Поисковый запрос на русском или английском.')
                ->required(),
            'project' => $schema->string()
                ->description('Фильтр по имени проекта, если он известен.'),
            'kinds' => $schema->array()
                ->items($schema->string()->enum([
                    'profile', 'preference', 'project', 'decision', 'person',
                    'company', 'stack', 'style', 'process', 'fact', 'other',
                ]))
                ->description('Ограничить типы знаний.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(50)
                ->description('Сколько записей вернуть (по умолчанию 8).'),
        ];
    }
}
