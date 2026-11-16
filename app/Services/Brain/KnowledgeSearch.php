<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\Dto\SearchResult;

/**
 * Search facade used by MCP tools, the API and the admin panel.
 */
final class KnowledgeSearch
{
    public function __construct(
        private readonly Bm25Search $engine,
    ) {}

    public function search(SearchQuery $query): SearchResult
    {
        return $this->engine->search($query);
    }
}
