<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

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

    /**
     * Карточка по id. Ищем явно через модель, а не route-model binding: на
     * модели висит скоуп владельца из контекста запроса, поэтому чужой id
     * честно даёт 404, а не отдаёт карточку по угаданному номеру.
     */
    public function show(string $entry): JsonResponse
    {
        $model = KnowledgeEntry::query()->find((int) $entry);

        if ($model === null) {
            abort(404, 'Запись не найдена.');
        }

        return response()->json(['data' => $model]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'content' => ['required', 'string', 'min:3', 'max:20000'],
            'details' => ['nullable', 'string', 'max:50000'],
            'kind' => ['nullable', 'string', Rule::in(array_keys(KnowledgeKind::options()))],
            'project' => ['nullable', 'string', 'max:128'],
            'theme' => ['nullable', 'string', 'max:64'],
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
                theme: $validated['theme'] ?? null,
                tags: $validated['tags'] ?? [],
                importance: (int) ($validated['importance'] ?? 3),
                confidence: 1.0,
            ),
            null,
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
            ],
        ]);
    }
}
