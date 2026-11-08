<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Models\Conversation;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Services\Brain\ProfileBuilder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('knowledge-index')]
#[Uri('knowledge://index')]
#[MimeType('application/json')]
#[Description('Обзор базы знаний: количество записей, проекты, последние знания и профиль пользователя.')]
class KnowledgeIndexResource extends Resource
{
    public function __construct(
        private readonly ProfileBuilder $profile,
    ) {}

    public function handle(Request $request): Response
    {
        $projects = KnowledgeEntry::query()
            ->approved()
            ->whereNotNull('project')
            ->where('project', '!=', '')
            ->groupBy('project')
            ->selectRaw('project, COUNT(*) as entries')
            ->orderByDesc('entries')
            ->pluck('entries', 'project')
            ->all();

        $recent = KnowledgeEntry::query()
            ->approved()
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'title', 'kind', 'project', 'updated_at']);

        return Response::json([
            'stats' => [
                'entries' => KnowledgeEntry::query()->approved()->count(),
                'drafts' => KnowledgeEntry::query()->where('status', 'draft')->count(),
                'chunks' => KnowledgeChunk::query()->count(),
                'conversations' => Conversation::query()->count(),
                'sources' => KnowledgeSource::query()->count(),
            ],
            'projects' => $projects,
            'recent' => $recent->map(fn (KnowledgeEntry $entry): array => [
                'id' => $entry->id,
                'title' => $entry->title,
                'kind' => $entry->kind->value,
                'project' => $entry->project,
                'updated_at' => $entry->updated_at?->toIso8601String(),
            ])->all(),
            'profile' => $this->profile->build(4000),
        ]);
    }
}
