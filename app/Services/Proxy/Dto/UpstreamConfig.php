<?php

declare(strict_types=1);

namespace App\Services\Proxy\Dto;

final readonly class UpstreamConfig
{
    public function __construct(
        public string $driver,
        public string $baseUrl,
        public string $apiKey,
        public string $model,
        public int $timeout,
        public bool $mock,
    ) {}

    public function isAnthropic(): bool
    {
        return $this->driver === 'anthropic';
    }

    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }
}
