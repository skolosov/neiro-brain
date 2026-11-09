<?php

declare(strict_types=1);

namespace App\Services\Brain\Dto;

use App\Enums\KnowledgeKind;
use App\Enums\KnowledgeScope;

final readonly class ExtractedEntry
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public KnowledgeKind $kind,
        public string $title,
        public string $content,
        public ?string $summary = null,
        public ?string $details = null,
        public ?string $project = null,
        public ?string $theme = null,
        public KnowledgeScope $scope = KnowledgeScope::Project,
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

        $project = isset($data['project']) && trim((string) $data['project']) !== ''
            ? trim((string) $data['project'])
            : $fallbackProject;

        return new self(
            kind: $kind,
            title: trim((string) ($data['title'] ?? '')),
            content: trim((string) ($data['content'] ?? '')),
            summary: isset($data['summary']) && trim((string) $data['summary']) !== ''
                ? trim((string) $data['summary'])
                : null,
            details: isset($data['details']) && trim((string) $data['details']) !== ''
                ? trim((string) $data['details'])
                : null,
            project: $project !== '' ? $project : null,
            theme: isset($data['theme']) && trim((string) $data['theme']) !== ''
                ? trim((string) $data['theme'])
                : null,
            scope: self::scopeOf($data['scope'] ?? null, $project, $kind),
            tags: $tags,
            importance: max(1, min(5, (int) ($data['importance'] ?? 3))),
            confidence: max(0.0, min(1.0, (float) ($data['confidence'] ?? 0.7))),
        );
    }

    /**
     * A card about the user himself or about his machines cannot belong to one
     * repository, whatever the model said: it would never reach a chat working
     * on another project.
     */
    private static function scopeOf(mixed $scope, ?string $project, KnowledgeKind $kind): KnowledgeScope
    {
        $resolved = is_string($scope) ? KnowledgeScope::tryFrom($scope) : null;

        if ($resolved === KnowledgeScope::Project && ($project === null || $project === '')) {
            return in_array($kind, [KnowledgeKind::Profile, KnowledgeKind::Preference, KnowledgeKind::Style], true)
                ? KnowledgeScope::Profile
                : KnowledgeScope::Project;
        }

        if ($resolved !== null) {
            return $resolved;
        }

        if ($project !== null && $project !== '') {
            return KnowledgeScope::Project;
        }

        return in_array($kind, [KnowledgeKind::Profile, KnowledgeKind::Preference, KnowledgeKind::Style], true)
            ? KnowledgeScope::Profile
            : KnowledgeScope::Project;
    }

    public function fingerprint(): string
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', $this->content) ?? $this->content);

        return hash('sha256', $this->kind->value.'|'.$normalized);
    }
}
