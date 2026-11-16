<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\EntryStatus;
use App\Enums\KnowledgeKind;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\KnowledgeSearch;
use App\Services\Brain\KnowledgeWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BrainApiController extends Controller
{
    public function __construct(
        private readonly KnowledgeSearch $search,
        private readonly KnowledgeWriter $writer,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:2000'],
            'project' => ['nullable', 'string', 'max:128'],
            'kinds' => ['nullable', 'array'],
            'kinds.*' => ['string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->search->search(new SearchQuery(
            query: $validated['q'],
            project: $validated['project'] ?? null,
            kinds: $validated['kinds'] ?? [],
            limit: (int) ($validated['limit'] ?? 8),
        ));

        return response()->json([
            'data' => $result->toArray(),
            'meta' => [
                'terms' => $result->terms,
                'candidates' => $result->candidates,
                'took_ms' => $result->tookMs,
            ],
        ]);
    }

    public function show(KnowledgeEntry $entry): JsonResponse
    {
        return response()->json(['data' => $entry]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'content' => ['required', 'string', 'min:3', 'max:20000'],
            'details' => ['nullable', 'string', 'max:50000'],
            'kind' => ['nullable', 'string', Rule::in(array_keys(KnowledgeKind::options()))],
            'status' => ['nullable', 'string', Rule::in(array_keys(EntryStatus::options()))],
            'project' => ['nullable', 'string', 'max:128'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'importance' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $result = $this->writer->write(
            new ExtractedEntry(
                kind: KnowledgeKind::tryFrom($validated['kind'] ?? 'fact') ?? KnowledgeKind::Fact,
                title: $validated['title'],
                content: $validated['content'],
                details: $validated['details'] ?? null,
                project: $validated['project'] ?? null,
                tags: $validated['tags'] ?? [],
                importance: (int) ($validated['importance'] ?? 3),
                confidence: 1.0,
            ),
            EntryStatus::tryFrom($validated['status'] ?? 'approved') ?? EntryStatus::Approved,
            ['engine' => 'api'],
        );

        if ($result === null) {
            return response()->json(['error' => 'Запись пуста после очистки секретов.'], 422);
        }

        return response()->json([
            'data' => $result['entry'],
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    public function projects(): JsonResponse
    {
        $projects = KnowledgeEntry::query()
            ->approved()
            ->whereNotNull('project')
            ->where('project', '!=', '')
            ->groupBy('project')
            ->select('project', DB::raw('COUNT(*) as entries'))
            ->orderByDesc('entries')
            ->get();

        return response()->json(['data' => $projects]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'entries' => KnowledgeEntry::query()->approved()->count(),
                'drafts' => KnowledgeEntry::query()->where('status', 'draft')->count(),
            ],
        ]);
    }
}
