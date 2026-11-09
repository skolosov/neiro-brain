<?php

declare(strict_types=1);

namespace App\Services\Brain\Dto;

final readonly class GraphEdge
{
    public function __construct(
        public string $source,
        public string $target,
        public int $weight,
    ) {}

    /**
     * Stable key that ignores direction: (1, 2) and (2, 1) collide.
     */
    public function key(): string
    {
        return $this->source < $this->target
            ? $this->source.'|'.$this->target
            : $this->target.'|'.$this->source;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'target' => $this->target,
            'weight' => $this->weight,
        ];
    }
}
