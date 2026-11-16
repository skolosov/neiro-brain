<?php

declare(strict_types=1);

namespace App\Services\Brain\Dto;

final readonly class SearchQuery
{
    /**
     * @param  list<string>  $kinds
     * @param  bool  $includeDrafts  Оставлен для совместимости вызовов: черновиков в потоке больше нет.
     */
    public function __construct(
        public string $query,
        public ?string $project = null,
        public array $kinds = [],
        public int $limit = 8,
        public bool $includeDrafts = false,
    ) {}
}
