<?php

declare(strict_types=1);

namespace App\Services\Brain\Dto;

final readonly class SearchResult
{
    /**
     * @param  list<SearchHit>  $hits
     * @param  list<string>  $terms
     */
    public function __construct(
        public string $query,
        public array $hits,
        public array $terms,
        public int $tookMs,
        public int $candidates,
    ) {}

    public function isEmpty(): bool
    {
        return $this->hits === [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (SearchHit $hit): array => $hit->toArray(), $this->hits);
    }
}
