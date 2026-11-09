<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\EntryStatus;
use App\Models\KnowledgeEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_knowledge')]
#[Description('Убирает карточки знаний по id. По умолчанию переводит в архив: карточка исчезает из поиска и из контекста, но остаётся в админке и её можно вернуть. mode=delete стирает навсегда вместе с поисковым индексом. Используй, когда пользователь просит удалить мусор или устаревшую карточку.')]
#[IsDestructive(true)]
class DeleteKnowledgeTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'min:1'],
            'mode' => ['nullable', 'string', Rule::in(['archive', 'delete'])],
        ]);

        $mode = (string) ($validated['mode'] ?? 'archive');
        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
        $entries = KnowledgeEntry::query()->whereIn('id', $ids)->get();

        if ($entries->isEmpty()) {
            return Response::error(sprintf('Карточки не найдены: %s.', $this->list($ids)));
        }

        $done = [];

        foreach ($entries as $entry) {
            if ($mode === 'archive') {
                $entry->forceFill(['status' => EntryStatus::Archived->value])->save();
            } else {
                // Chunks and terms follow the foreign keys; doing it explicitly
                // keeps the index clean even where the driver leaves cascades off.
                $entry->chunks()->delete();
                $entry->delete();
            }

            $done[] = (int) $entry->id;
        }

        $missing = array_values(array_diff($ids, $done));

        $message = $mode === 'archive'
            ? sprintf('Переведено в архив: %s.', $this->list($done))
            : sprintf('Удалено: %s.', $this->list($done));

        if ($missing !== []) {
            $message .= sprintf(' Не найдено: %s.', $this->list($missing));
        }

        return Response::text($message);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'ids' => $schema->array()
                ->items($schema->integer()->min(1))
                ->description('Id карточек из search_knowledge или recent_knowledge.')
                ->required(),
            'mode' => $schema->string()
                ->enum(['archive', 'delete'])
                ->description('archive (по умолчанию) — спрятать с возможностью вернуть; delete — стереть навсегда.'),
        ];
    }

    /**
     * @param  list<int>  $ids
     */
    private function list(array $ids): string
    {
        return implode(', ', array_map(fn (int $id): string => '#'.$id, $ids));
    }
}
