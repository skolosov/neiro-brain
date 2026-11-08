<?php

declare(strict_types=1);

namespace App\Services\Brain\Dto;

use App\Enums\KnowledgeKind;

final readonly class ExtractedEntry
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public KnowledgeKind $kind,
        public string $title,
        public string $content,
        public ?string $details = null,
        public ?string $project = null,
        public array $tags = [],
        public int $importance = 3,
        public float $confidence = 0.7,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $fallbackProject = null): self
    {
        $kind = KnowledgeKind::tryFrom((string) ($data['kind'] ?? 'fact')) ?? KnowledgeKind::Fact;

        $tags = $data['tags'] ?? [];

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        $tags = collect(is_array($tags) ? $tags : [])
            ->map(fn ($tag): string => trim((string) $tag))
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();

        return new self(
            kind: $kind,
            title: trim((string) ($data['title'] ?? '')),
            content: trim((string) ($data['content'] ?? '')),
            details: isset($data['details']) && trim((string) $data['details']) !== ''
                ? trim((string) $data['details'])
                : null,
            project: isset($data['project']) && trim((string) $data['project']) !== ''
                ? trim((string) $data['project'])
                : $fallbackProject,
            tags: $tags,
            importance: max(1, min(5, (int) ($data['importance'] ?? 3))),
            confidence: max(0.0, min(1.0, (float) ($data['confidence'] ?? 0.7))),
        );
    }

    public function fingerprint(): string
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', $this->content) ?? $this->content);

        return hash('sha256', $this->kind->value.'|'.$normalized);
    }
}
