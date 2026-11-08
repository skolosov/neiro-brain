<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\KnowledgeEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_projects')]
#[Description('Список проектов, о которых есть знания, с количеством записей. Помогает выбрать проект для фильтрации поиска.')]
class ListProjectsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $projects = KnowledgeEntry::query()
            ->approved()
            ->whereNotNull('project')
            ->where('project', '!=', '')
            ->groupBy('project')
            ->select('project', DB::raw('COUNT(*) as entries'), DB::raw('MAX(updated_at) as last_updated'))
            ->orderByDesc('entries')
            ->get();

        if ($projects->isEmpty()) {
            return Response::text('Проекты в базе знаний пока не отмечены.');
        }

        $lines = ['Проекты в базе знаний:', ''];

        foreach ($projects as $project) {
            $lines[] = sprintf('- %s — %d записей (обновлён %s)', $project->project, $project->entries, $project->last_updated);
        }

        return Response::make(Response::text(implode("\n", $lines)))->withStructuredContent([
            'projects' => $projects->map(fn ($project): array => [
                'project' => $project->project,
                'entries' => (int) $project->entries,
                'last_updated' => $project->last_updated,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
