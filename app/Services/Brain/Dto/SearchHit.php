<?php

declare(strict_types=1);

namespace App\Services\Brain\Dto;

use App\Enums\KnowledgeKind;
use App\Models\KnowledgeEntry;

final readonly class SearchHit
{
    /**
     * @param  list<string>  $matchedTerms
     */
    public function __construct(
        public KnowledgeEntry $entry,
        public float $score,
        public string $snippet,
        public array $matchedTerms,
        public int $chunkId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->entry->id,
            'title' => $this->entry->title,
            'content' => $this->entry->content,
            'kind' => $this->entry->kind instanceof KnowledgeKind
                ? $this->entry->kind->value
                : (string) $this->entry->kind,
            'project' => $this->entry->project,
            'tags' => $this->entry->tags ?? [],
            'importance' => $this->entry->importance,
            'score' => round($this->score, 4),
            'snippet' => $this->snippet,
            'matched_terms' => $this->matchedTerms,
            'occurred_at' => $this->entry->occurred_at?->toIso8601String(),
        ];
    }
}
