<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Models\SearchLog;
use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\Dto\SearchResult;
use App\Settings\RetrievalSettings;

/**
 * Search facade used by MCP tools, the proxy, the API and the admin panel.
 */
final class KnowledgeSearch
{
    public function __construct(
        private readonly Bm25Search $engine,
        private readonly RetrievalSettings $settings,
    ) {}

    public function search(SearchQuery $query, string $source = 'admin'): SearchResult
    {
        $result = $this->engine->search($query);

        if ($this->settings->log_searches) {
            SearchLog::query()->create([
                'query' => mb_substr($query->query, 0, 250),
                'normalized_query' => mb_substr(implode(' ', $result->terms), 0, 250),
                'source' => $source,
                'results_count' => count($result->hits),
                'top_entry_id' => $result->hits[0]->entry->id ?? null,
                'latency_ms' => $result->tookMs,
                'filters' => array_filter([
                    'project' => $query->project,
                    'kinds' => $query->kinds,
                ]),
            ]);
        }

        return $result;
    }
}
