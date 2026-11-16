<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\KnowledgeKind;
use App\Enums\KnowledgeScope;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Throwable;

/**
 * Пачечная запись знаний — единственный путь записи из MCP.
 *
 * Одна карточка = один факт. Дубли не дописываются рядом: старая карточка
 * помечается `superseded_by` и остаётся историей. Ошибка в одной карточке не
 * отменяет остальные — ответ строится по каждой строке пачки.
 */
#[Name('remember_many')]
#[Description('Сохраняет пачку фактов в личную базу знаний: от 1 до 20 карточек за вызов. Сначала вызови search_knowledge по теме. Одна карточка = один факт; если такой факт уже есть, не дописывай вторую карточку рядом, а замени старую: передай её id в поле supersedes — она станет историей и исчезнет из поиска. Это единственный способ слияния дублей. Ответ содержит статус по каждой карточке: created, updated или rejected с причиной.')]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class RememberManyTool extends Tool
{
    public function __construct(
        private readonly KnowledgeWriter $writer,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'items' => ['present', 'array', 'max:20'],
        ], [
            'items.present' => 'Передайте items — массив карточек.',
            'items.array' => 'items должен быть массивом карточек.',
            'items.max' => 'В одной пачке не больше 20 карточек.',
        ]);

        /** @var array<int, mixed> $items */
        $items = array_values($validated['items']);

        if ($items === []) {
            return Response::error('Пачка пустая: передайте от 1 до 20 карточек в items.');
        }

        $created = 0;
        $updated = 0;
        $rejected = 0;
        $lines = [];
        $results = [];

        foreach ($items as $index => $item) {
            $number = $index + 1;

            if (! is_array($item)) {
                $rejected++;
                $reason = 'карточка должна быть объектом';
                $lines[] = sprintf('- Карточка %d: отклонена — %s.', $number, $reason);
                $results[] = [
                    'index' => $number,
                    'status' => 'rejected',
                    'reason' => $reason,
                ];

                continue;
            }

            $validator = Validator::make($item, $this->rules(), $this->messages());

            if ($validator->fails()) {
                $rejected++;
                $reason = (string) $validator->errors()->first();
                $title = is_string($item['title'] ?? null) ? trim($item['title']) : '';
                $lines[] = sprintf(
                    '- Карточка %d%s: отклонена — %s.',
                    $number,
                    $title !== '' ? ' «'.$title.'»' : '',
                    $reason,
                );
                $results[] = [
                    'index' => $number,
                    'status' => 'rejected',
                    'title' => $title !== '' ? $title : null,
                    'reason' => $reason,
                ];

                continue;
            }

            /** @var array<string, mixed> $data */
            $data = $validator->validated();

            $project = isset($data['project']) && trim((string) $data['project']) !== ''
                ? trim((string) $data['project'])
                : null;

            $scope = KnowledgeScope::tryFrom((string) ($data['scope'] ?? ''))
                ?? ($project !== null ? KnowledgeScope::Project : KnowledgeScope::Profile);

            if ($scope === KnowledgeScope::Project && $project === null) {
                $rejected++;
                $reason = 'для scope=project укажите project';
                $lines[] = sprintf('- Карточка %d: отклонена — %s.', $number, $reason);
                $results[] = [
                    'index' => $number,
                    'status' => 'rejected',
                    'title' => (string) $data['title'],
                    'reason' => $reason,
                ];

                continue;
            }

            try {
                $result = $this->writer->write(
                    new ExtractedEntry(
                        kind: KnowledgeKind::tryFrom((string) ($data['kind'] ?? 'fact')) ?? KnowledgeKind::Fact,
                        title: (string) $data['title'],
                        content: (string) $data['content'],
                        summary: isset($data['summary']) && trim((string) $data['summary']) !== ''
                            ? trim((string) $data['summary'])
                            : null,
                        details: isset($data['details']) && trim((string) $data['details']) !== ''
                            ? trim((string) $data['details'])
                            : null,
                        project: $project,
                        theme: isset($data['theme']) && trim((string) $data['theme']) !== ''
                            ? trim((string) $data['theme'])
                            : null,
                        scope: $scope,
                        tags: $data['tags'] ?? [],
                        importance: (int) ($data['importance'] ?? 3),
                        confidence: 1.0,
                    ),
                    null,
                    ['engine' => 'mcp'],
                );
            } catch (Throwable $exception) {
                report($exception);

                $rejected++;
                $reason = 'не удалось записать карточку';
                $lines[] = sprintf('- Карточка %d: отклонена — %s.', $number, $reason);
                $results[] = [
                    'index' => $number,
                    'status' => 'rejected',
                    'title' => (string) $data['title'],
                    'reason' => $reason,
                ];

                continue;
            }

            if ($result === null) {
                $rejected++;
                $reason = 'пустой текст после обработки';
                $lines[] = sprintf('- Карточка %d: отклонена — %s.', $number, $reason);
                $results[] = [
                    'index' => $number,
                    'status' => 'rejected',
                    'title' => (string) $data['title'],
                    'reason' => $reason,
                ];

                continue;
            }

            $entry = $result['entry'];
            $linked = [];
            $ignored = [];

            if (isset($data['supersedes']) && is_array($data['supersedes']) && $data['supersedes'] !== []) {
                try {
                    $superseded = $this->writer->supersedeWith($entry, $data['supersedes']);
                    $linked = $superseded['linked'];
                    $ignored = $superseded['ignored'];
                } catch (Throwable $exception) {
                    report($exception);
                    $ignored = array_values(array_map('intval', $data['supersedes']));
                }
            }

            if ($result['created']) {
                $created++;
                $status = 'created';
                $label = 'создана';
            } else {
                $updated++;
                $status = 'updated';
                $label = 'обновлена существующая';
            }

            $line = sprintf('- «%s»: %s, #%d.', $entry->title, $label, $entry->id);

            if ($linked !== []) {
                $line .= ' Заменены: '.$this->list($linked).'.';
            }

            if ($ignored !== []) {
                $line .= ' Пропущены чужие или несуществующие: '.$this->list($ignored).'.';
            }

            $lines[] = $line;
            $results[] = [
                'index' => $number,
                'status' => $status,
                'id' => (int) $entry->id,
                'title' => $entry->title,
                'superseded' => $linked,
                'ignored' => $ignored,
            ];
        }

        $summary = sprintf('создано %d, обновлено %d, отклонено %d', $created, $updated, $rejected);
        $text = $summary."\n\n".implode("\n", $lines);

        return Response::make(Response::text($text))->withStructuredContent([
            'summary' => [
                'created' => $created,
                'updated' => $updated,
                'rejected' => $rejected,
            ],
            'items' => $results,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items($schema->object([
                    'title' => $schema->string()->min(3)->max(120)->description('Короткий заголовок факта.')->required(),
                    'content' => $schema->string()->min(3)->max(10000)->description('Сам факт, сформулированный в третьем лице.')->required(),
                    'summary' => $schema->string()->description('Необязательно: 1–2 предложения с самим ответом. Без него берутся первые 300 символов content.'),
                    'details' => $schema->string()->description('Необязательно: детали, которых нет в summary.'),
                    'kind' => $schema->string()->enum([
                        'profile', 'preference', 'project', 'decision', 'person',
                        'company', 'stack', 'style', 'process', 'fact', 'other',
                    ]),
                    'project' => $schema->string()->description('Проект, к которому относится факт.'),
                    'scope' => $schema->string()
                        ->enum(['profile', 'project', 'environment'])
                        ->description('profile — про пользователя, environment — про машины и окружение, project — про один проект.'),
                    'theme' => $schema->string()->description('Одно слово-тема из канонического списка.'),
                    'tags' => $schema->array()->items($schema->string())->max(3),
                    'importance' => $schema->integer()->min(1)->max(5),
                    'supersedes' => $schema->array()
                        ->items($schema->integer()->min(1))
                        ->description('Id своих карточек, которые эта карточка заменяет. Они станут историей и исчезнут из поиска.'),
                ]))
                ->description('Карточки пачки: от 1 до 20 штук. Одна карточка = один факт.')
                ->required(),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'content' => ['required', 'string', 'min:3', 'max:10000'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'details' => ['nullable', 'string', 'max:20000'],
            'kind' => ['nullable', 'string', Rule::in(array_keys(KnowledgeKind::options()))],
            'project' => ['nullable', 'string', 'max:128'],
            'scope' => ['nullable', 'string', Rule::in(array_keys(KnowledgeScope::options()))],
            'theme' => ['nullable', 'string', 'max:64'],
            'tags' => ['nullable', 'array', 'max:3'],
            'tags.*' => ['string', 'max:64'],
            'importance' => ['nullable', 'integer', 'min:1', 'max:5'],
            'supersedes' => ['nullable', 'array', 'max:50'],
            'supersedes.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'title.required' => 'нужен заголовок',
            'title.string' => 'заголовок должен быть строкой',
            'title.min' => 'заголовок короче 3 символов',
            'title.max' => 'заголовок длиннее 120 символов',
            'content.required' => 'нужен content',
            'content.string' => 'content должен быть строкой',
            'content.min' => 'content короче 3 символов',
            'content.max' => 'content длиннее 10000 символов',
            'summary.max' => 'summary длиннее 1000 символов',
            'details.max' => 'details длиннее 20000 символов',
            'kind.in' => 'неизвестный kind',
            'project.max' => 'project длиннее 128 символов',
            'scope.in' => 'неизвестный scope',
            'theme.max' => 'тема длиннее 64 символов',
            'tags.array' => 'tags должен быть массивом',
            'tags.max' => 'тегов больше трёх',
            'tags.*.string' => 'тег должен быть строкой',
            'tags.*.max' => 'тег длиннее 64 символов',
            'importance.integer' => 'importance должен быть числом',
            'importance.min' => 'importance меньше 1',
            'importance.max' => 'importance больше 5',
            'supersedes.array' => 'supersedes должен быть массивом id',
            'supersedes.max' => 'в supersedes больше 50 id',
            'supersedes.*.integer' => 'supersedes принимает только id',
            'supersedes.*.min' => 'id в supersedes должен быть положительным',
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
