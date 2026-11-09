<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Enums\ConversationStatus;
use App\Enums\EntryStatus;
use App\Models\Conversation;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Models\SearchLog;
use App\Models\SyncRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the overview page shows, in one place.
 *
 * The numbers are deliberately "honest": a card counts only when it is in the
 * index and approved, and the chart counts cards by the date of the knowledge
 * (occurred_at) rather than by the moment of import.
 */
final class BrainOverview
{
    public function __construct(
        private readonly TopicMap $topics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $entries = KnowledgeEntry::query();
        $published = KnowledgeEntry::query()->approved();

        $total = (clone $entries)->count();
        $approved = (clone $published)->count();
        $indexed = (clone $published)->whereNotNull('indexed_at')->count();

        return [
            'total' => $total,
            'approved' => $approved,
            'drafts' => (clone $entries)->where('status', EntryStatus::Draft->value)->count(),
            'indexed' => $indexed,
            'indexed_share' => $total > 0 ? (int) round($indexed / max(1, $approved) * 100) : 0,
            'projects' => (clone $published)->whereNotNull('project')->where('project', '!=', '')->distinct('project')->count('project'),
            'topics' => $this->topics->counts(12),
            'chunks' => KnowledgeChunk::query()->count(),
            'conversations' => Conversation::query()->count(),
            'pending' => Conversation::query()->where('status', ConversationStatus::Pending->value)->count(),
            'summarized' => Conversation::query()->where('status', ConversationStatus::Summarized->value)->count(),
            'searches24h' => SearchLog::query()->where('created_at', '>=', now()->subDay())->count(),
            'lastRun' => SyncRun::query()->latest('id')->first(),
            'series' => $this->series(),
            'projectsCards' => $this->projectCards(),
            'sources' => $this->sources(),
            'recent' => KnowledgeEntry::query()
                ->approved()
                ->orderByDesc('updated_at')
                ->limit(6)
                ->get(),
            'recentSearches' => SearchLog::query()
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
        ];
    }

    /**
     * Cards per week for the last 12 weeks.
     *
     * @return list<array{label: string, value: int}>
     */
    public function series(int $weeks = 12): array
    {
        $start = now()->startOfWeek()->subWeeks($weeks - 1);

        $rows = DB::table('knowledge_entries')
            ->whereIn('status', [EntryStatus::Approved->value, EntryStatus::Draft->value])
            ->where(function ($query) use ($start): void {
                $query->where('occurred_at', '>=', $start)->orWhere('created_at', '>=', $start);
            })
            ->selectRaw('COALESCE(occurred_at, created_at) as happened_at')
            ->pluck('happened_at');

        $buckets = [];

        foreach ($rows as $value) {
            $moment = Carbon::parse((string) $value);
            $key = $moment->startOfWeek()->toDateString();
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        $series = [];

        for ($i = 0; $i < $weeks; $i++) {
            $week = $start->copy()->addWeeks($i);
            $series[] = [
                'label' => $week->format('d.m'),
                'value' => $buckets[$week->toDateString()] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function projectCards(int $limit = 12): Collection
    {
        $entries = KnowledgeEntry::query()
            ->approved()
            ->whereNotNull('project')
            ->where('project', '!=', '')
            ->orderByDesc('importance')
            ->get();

        return $entries
            ->groupBy(fn (KnowledgeEntry $entry): string => trim((string) $entry->project))
            ->map(function (Collection $items, string $project): array {
                $topics = [];

                foreach (app(TopicMap::class)->group($items) as $topic => $topicEntries) {
                    $topics[$topic] = $topicEntries->count();
                }

                arsort($topics);

                return [
                    'name' => $project,
                    'count' => $items->count(),
                    'topics' => array_slice($topics, 0, 6, true),
                    'importance' => (int) round($items->avg('importance')),
                    'last' => $items->max(fn (KnowledgeEntry $entry) => $entry->occurred_at ?? $entry->updated_at),
                    'url' => route('moonshine.page', ['pageUri' => 'search-page', 'project' => $project]),
                ];
            })
            ->sortByDesc('count')
            ->take($limit)
            ->values();
    }

    /**
     * Source health: how many conversations each source produced and whether it
     * errors out.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sources(): Collection
    {
        return KnowledgeSource::query()
            ->withCount('conversations')
            ->orderBy('name')
            ->get()
            ->map(fn (KnowledgeSource $source): array => [
                'name' => (string) $source->name,
                'slug' => (string) $source->slug,
                'type' => $source->type instanceof \BackedEnum ? (string) $source->type->value : (string) $source->type,
                'enabled' => (bool) $source->enabled,
                'conversations' => (int) $source->conversations_count,
                'cards' => KnowledgeEntry::query()->where('source_id', $source->id)->count(),
                'last_synced_at' => $source->last_synced_at,
                'error' => $source->last_error,
            ]);
    }
}
