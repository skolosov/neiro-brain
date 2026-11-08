<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\EntryStatus;
use App\Enums\KnowledgeKind;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Services\Brain\SourceRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('remember_knowledge')]
#[Description('Сохраняет новый долговременный факт о пользователе в базу знаний. Используй, когда пользователь явно просит запомнить что-то важное.')]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class RememberKnowledgeTool extends Tool
{
    public function __construct(
        private readonly KnowledgeWriter $writer,
        private readonly SourceRegistry $sources,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'content' => ['required', 'string', 'min:3', 'max:10000'],
            'kind' => ['nullable', 'string'],
            'project' => ['nullable', 'string', 'max:128'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'importance' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $kind = KnowledgeKind::tryFrom((string) ($validated['kind'] ?? 'fact')) ?? KnowledgeKind::Fact;

        $result = $this->writer->write(
            new ExtractedEntry(
                kind: $kind,
                title: $validated['title'],
                content: $validated['content'],
                project: $validated['project'] ?? null,
                tags: $validated['tags'] ?? [],
                importance: (int) ($validated['importance'] ?? 3),
                confidence: 1.0,
            ),
            source: $this->sources->manual(),
            status: EntryStatus::Approved,
            metadata: ['engine' => 'mcp'],
        );

        if ($result === null) {
            return Response::error('Не удалось сохранить запись: пустой текст.');
        }

        return Response::text(sprintf(
            '%s запись #%d: «%s».',
            $result['created'] ? 'Создана' : 'Обновлена существующая',
            $result['entry']->id,
            $result['entry']->title,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Короткий заголовок факта.')->required(),
            'content' => $schema->string()->description('Сам факт, сформулированный в третьем лице.')->required(),
            'kind' => $schema->string()->enum([
                'profile', 'preference', 'project', 'decision', 'person',
                'company', 'stack', 'style', 'process', 'fact', 'other',
            ]),
            'project' => $schema->string()->description('Проект, к которому относится факт.'),
            'tags' => $schema->array()->items($schema->string()),
            'importance' => $schema->integer()->min(1)->max(5),
        ];
    }
}
