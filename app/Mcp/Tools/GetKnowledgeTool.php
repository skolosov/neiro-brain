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

#[Name('get_knowledge')]
#[Description('Возвращает полную запись базы знаний по её id. Используй после search_knowledge, если нужен полный текст, детали или метаданные.')]
class GetKnowledgeTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $entry = KnowledgeEntry::query()->find($validated['id']);

        if ($entry === null) {
            return Response::error('Запись #'.$validated['id'].' не найдена.');
        }

        return Response::make(Response::text($this->render($entry)))->withStructuredContent([
            'entry' => [
                'id' => $entry->id,
                'title' => $entry->title,
                'content' => $entry->content,
                'details' => $entry->details,
                'kind' => $entry->kind->value,
                'status' => $entry->status->value,
                'project' => $entry->project,
                'tags' => $entry->tags ?? [],
                'importance' => $entry->importance,
                'confidence' => $entry->confidence,
                'occurred_at' => $entry->occurred_at?->toIso8601String(),
                'updated_at' => $entry->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID записи из результата search_knowledge.')
                ->required(),
        ];
    }

    private function render(KnowledgeEntry $entry): string
    {
        $lines = [
            '# ['.$entry->id.'] '.$entry->title,
            '',
            '**Тип:** '.$entry->kind->label(),
            '**Статус:** '.$entry->status->label(),
            '**Проект:** '.($entry->project ?? '—'),
            '**Важность:** '.$entry->importance,
            '**Теги:** '.(implode(', ', $entry->tags ?? []) ?: '—'),
            '',
            $entry->content,
        ];

        if ($entry->details !== null && $entry->details !== '') {
            $lines[] = '';
            $lines[] = '## Детали';
            $lines[] = $entry->details;
        }

        return implode("\n", $lines);
    }
}
