<?php

declare(strict_types=1);

namespace App\Services\Brain\Dto;

final readonly class GraphData
{
    /**
     * @param  list<GraphNode>  $nodes
     * @param  list<GraphEdge>  $edges
     * @param  list<string>  $projects
     * @param  list<string>  $tags
     */
    public function __construct(
        public array $nodes,
        public array $edges,
        public array $projects,
        public array $tags,
        public int $truncated = 0,
    ) {}

    public function isEmpty(): bool
    {
        return $this->nodes === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nodes' => array_map(fn (GraphNode $node): array => $node->toArray(), $this->nodes),
            'edges' => array_map(fn (GraphEdge $edge): array => $edge->toArray(), $this->edges),
            'projects' => $this->projects,
            'tags' => $this->tags,
            'truncated' => $this->truncated,
        ];
    }

    /**
     * @return array<string, GraphNode>
     */
    public function nodeIndex(): array
    {
        $index = [];

        foreach ($this->nodes as $node) {
            $index[$node->id] = $node;
        }

        return $index;
    }
}
