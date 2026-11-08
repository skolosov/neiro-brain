<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\Conversation;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Models\SearchLog;
use App\Models\SyncRun;
use Illuminate\Contracts\Support\Renderable;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\FlexibleRender;

class Dashboard extends Page
{
    public function getTitle(): string
    {
        return 'Обзор';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return ['#' => $this->getTitle()];
    }

    /**
     * @return iterable<Renderable|string>
     */
    protected function components(): iterable
    {
        $projects = KnowledgeEntry::query()
            ->approved()
            ->whereNotNull('project')
            ->where('project', '!=', '')
            ->groupBy('project')
            ->selectRaw('project, COUNT(*) as entries')
            ->orderByDesc('entries')
            ->limit(10)
            ->get();

        $data = [
            'entries' => KnowledgeEntry::query()->count(),
            'approved' => KnowledgeEntry::query()->approved()->count(),
            'drafts' => KnowledgeEntry::query()->where('status', 'draft')->count(),
            'conversations' => Conversation::query()->count(),
            'pending' => Conversation::query()->where('status', 'pending')->count(),
            'chunks' => KnowledgeChunk::query()->count(),
            'sources' => KnowledgeSource::query()->count(),
            'searches24h' => SearchLog::query()->where('created_at', '>=', now()->subDay())->count(),
            'lastRun' => SyncRun::query()->latest('id')->first(),
            'recent' => KnowledgeEntry::query()->latest('updated_at')->limit(8)->get(),
            'projects' => $projects,
            'pendingConversations' => Conversation::query()
                ->where('status', 'pending')
                ->orderBy('last_activity_at')
                ->limit(5)
                ->get(),
        ];

        return [
            FlexibleRender::make(fn (): string => view('moonshine.brain.dashboard', $data)->render()),
        ];
    }
}
