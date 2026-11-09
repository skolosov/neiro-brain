<?php

declare(strict_types=1);

namespace App\Services\Brain\Dto;

final readonly class GraphNode
{
    /**
     * @param  list<string>  $tags
     * @param  list<int>  $chunkIds
     * @param  list<string>  $keywords  rare terms that make this node prominent
     */
    public function __construct(
        public string $id,
        public string $type,
        public int $refId,
        public string $label,
        public string $subtitle,
        public string $kind,
        public string $status,
        public int $importance,
        public string $project,
        public array $tags,
        public string $summary,
        public int $chunks,
        public int $terms,
        public float $size,
        public ?int $timestamp,
        public string $url,
        public array $chunkIds,
        public array $keywords = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'ref_id' => $this->refId,
            'label' => $this->label,
            'subtitle' => $this->subtitle,
            'kind' => $this->kind,
            'status' => $this->status,
            'importance' => $this->importance,
            'project' => $this->project,
            'tags' => $this->tags,
            'summary' => $this->summary,
            'chunks' => $this->chunks,
            'terms' => $this->terms,
            'size' => round($this->size, 2),
            'timestamp' => $this->timestamp,
            'url' => $this->url,
            'chunk_ids' => $this->chunkIds,
            'keywords' => $this->keywords,
        ];
    }
}
