<?php

declare(strict_types=1);

namespace App\Services\Brain\Dto;

final readonly class MaskResult
{
    /**
     * @param  array<string, int>  $redactions
     */
    public function __construct(
        public string $text,
        public array $redactions,
        public int $total,
    ) {}

    public function hasRedactions(): bool
    {
        return $this->total > 0;
    }
}
