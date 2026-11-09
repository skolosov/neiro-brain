<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\Brain\ProjectContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * Hands the model the user's own context: who he is, how his machines work and
 * what a project consists of — as a table of contents, not full text.
 */
#[Name('get_project_context')]
#[Description('Выдаёт карточки-оглавление: кто пользователь (стек, подходы, предпочтения), как устроены его машины и из каких тем состоит проект. Вызывай в начале разговора о пользователе или проекте, чтобы не переспрашивать заново. Полный текст карточки — через get_knowledge.')]
class ProjectContextTool extends Tool
{
    public function __construct(
        private readonly ProjectContext $context,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project' => ['nullable', 'string', 'max:128'],
        ]);

        $overview = $this->context->overview($validated['project'] ?? null);

        return Response::make(Response::text($this->render($overview)))->withStructuredContent($overview);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()
                ->description('Имя проекта, если разговор идёт о конкретном репозитории. Без него вернётся только профиль пользователя и окружение.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $overview
     */
    private function render(array $overview): string
    {
        $lines = ['# Что известно о пользователе', ''];

        $lines = [...$lines, ...$this->section('Кто пользователь', $overview['profile'] ?? [])];
        $lines = [...$lines, ...$this->section('Окружение и машины', $overview['environment'] ?? [])];

        $project = $overview['project'] ?? null;

        if (is_array($project) && ($project['cards'] ?? []) !== []) {
            $lines[] = '## Проект '.$project['name'];
            $lines[] = '';
            $lines = [...$lines, ...$this->cards($project['cards'])];
        }

        $projects = $overview['projects'] ?? [];

        if ($projects !== []) {
            $lines[] = '## Проекты в базе';
            $lines[] = '';

            foreach ($projects as $item) {
                $lines[] = sprintf('- %s — %d карточек', $item['name'], $item['cards']);
            }

            $lines[] = '';
        }

        $lines[] = 'Это оглавление. Полный текст любой карточки — вызов get_knowledge с её id.';

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<string>
     */
    private function section(string $title, array $cards): array
    {
        if ($cards === []) {
            return [];
        }

        return ['## '.$title, '', ...$this->cards($cards)];
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<string>
     */
    private function cards(array $cards): array
    {
        $lines = [];
        $theme = null;

        foreach ($cards as $card) {
            if (($card['theme'] ?? null) !== $theme) {
                $theme = $card['theme'];

                if ($theme !== null && $theme !== '') {
                    $lines[] = '**'.$theme.'**';
                }
            }

            $lines[] = sprintf('- [%d] %s — %s', $card['id'], $card['title'], $card['summary']);
        }

        $lines[] = '';

        return $lines;
    }
}
