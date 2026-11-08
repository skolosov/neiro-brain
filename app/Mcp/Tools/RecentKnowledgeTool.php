<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\KnowledgeEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('recent_knowledge')]
#[Description('Последние добавленные или обновлённые знания. Полезно в начале сессии, чтобы понять текущий контекст пользователя.')]
class RecentKnowledgeTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'project' => ['nullable', 'string', 'max:128'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $entries = KnowledgeEntry::query()
            ->approved()
            ->where('updated_at', '>=', now()->subDays((int) ($validated['days'] ?? 30)))
            ->when(isset($validated['project']), fn ($query) => $query->where('project', $validated['project']))
            ->orderByDesc('updated_at')
            ->limit((int) ($validated['limit'] ?? 10))
            ->get();

        if ($entries->isEmpty()) {
            return Response::text('За указанный период новых знаний нет.');
        }

        $lines = ['Последние знания:', ''];

        foreach ($entries as $entry) {
            $lines[] = sprintf(
                '- [%d] %s (%s, %s) — %s',
                $entry->id,
                $entry->title,
                $entry->kind->label(),
                $entry->project ?? 'без проекта',
                mb_substr($entry->content, 0, 220),
            );
        }

        return Response::make(Response::text(implode("\n", $lines)))->withStructuredContent([
            'entries' => $entries->map(fn (KnowledgeEntry $entry): array => [
                'id' => $entry->id,
                'title' => $entry->title,
                'kind' => $entry->kind->value,
                'project' => $entry->project,
                'content' => $entry->content,
                'updated_at' => $entry->updated_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->min(1)->max(365)->description('Период в днях (по умолчанию 30).'),
            'project' => $schema->string()->description('Фильтр по проекту.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Сколько записей вернуть.'),
        ];
    }
}
