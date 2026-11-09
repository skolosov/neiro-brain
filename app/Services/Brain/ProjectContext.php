<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Enums\KnowledgeScope;
use App\Models\KnowledgeEntry;
use Illuminate\Support\Collection;

/**
 * Assembles the picture the model starts a chat with.
 *
 * Two ideas hold it together:
 *
 *  1. Browse cheap, read deep. Every card is listed by title and summary only
 *     (a table of contents); full text is fetched on demand for the few cards
 *     that matter. Reading every card in full on every chat would cost ~13k
 *     tokens today and grow without limit.
 *
 *  2. Cross-project knowledge is always included. Profile and environment
 *     cards (the user's stack, his machines, how he runs commands) apply to
 *     every project, so a chat about dCell must see them even though they were
 *     learned in a chat about SMSGateway.
 *
 * This is deliberately separate from ContextBuilder, which renders search hits
 * into the proxy's prompt block.
 */
final class ProjectContext
{
    /**
     * Everything the model should know before the first question of a chat:
     * who the user is, how his machines work, and one project's table of
     * contents.
     *
     * @return array{
     *     profile: list<array<string, mixed>>,
     *     environment: list<array<string, mixed>>,
     *     project: array{name: string, cards: list<array<string, mixed>>}|null,
     *     projects: list<array{name: string, cards: int}>
     * }
     */
    public function overview(?string $project = null): array
    {
        $project = $project !== null && trim($project) !== '' ? trim($project) : null;

        return [
            'profile' => $this->cardsOfScope(KnowledgeScope::Profile),
            'environment' => $this->cardsOfScope(KnowledgeScope::Environment),
            'project' => $project !== null ? [
                'name' => $project,
                'cards' => $this->projectCards($project),
            ] : null,
            'projects' => $this->projects(),
        ];
    }

    /**
     * Table of contents of one project: title, theme and summary per card.
     *
     * @return list<array<string, mixed>>
     */
    public function projectCards(string $project, int $limit = 60): array
    {
        return $this->digest(
            KnowledgeEntry::query()
                ->approved()
                ->where('project', $project)
                ->orderBy('theme')
                ->orderByDesc('importance')
                ->limit($limit)
                ->get()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cardsOfScope(KnowledgeScope $scope, int $limit = 40): array
    {
        return $this->digest(
            KnowledgeEntry::query()
                ->approved()
                ->where('scope', $scope->value)
                ->orderBy('theme')
                ->orderByDesc('importance')
                ->limit($limit)
                ->get()
        );
    }

    /**
     * Cards as title + summary. This is the cheap level: ~80 tokens per card
     * instead of ~500 for the full text.
     *
     * @param  Collection<int, KnowledgeEntry>  $entries
     * @return list<array<string, mixed>>
     */
    private function digest(Collection $entries): array
    {
        return $entries
            ->map(fn (KnowledgeEntry $entry): array => [
                'id' => (int) $entry->id,
                'title' => (string) $entry->title,
                'theme' => $entry->theme ?? ($entry->tags[0] ?? null),
                'summary' => $entry->summary !== null && trim((string) $entry->summary) !== ''
                    ? (string) $entry->summary
                    : Text::limit((string) $entry->content, 240),
                'kind' => $entry->kind?->value,
                'importance' => (int) $entry->importance,
            ])
            ->values()
            ->all();
    }

    /**
     * Project names with card counts, for the list the model picks from.
     *
     * @return list<array{name: string, cards: int}>
     */
    public function projects(): array
    {
        return KnowledgeEntry::query()
            ->approved()
            ->whereNotNull('project')
            ->where('project', '!=', '')
            ->selectRaw('project as name, count(*) as cards')
            ->groupBy('project')
            ->orderByDesc('cards')
            ->get()
            ->map(fn ($row): array => [
                'name' => (string) $row->name,
                'cards' => (int) $row->cards,
            ])
            ->values()
            ->all();
    }

    /**
     * Full text of one card, the deep read after a summary looked promising.
     *
     * @return array<string, mixed>|null
     */
    public function full(int $id): ?array
    {
        $entry = KnowledgeEntry::query()->approved()->find($id);

        if ($entry === null) {
            return null;
        }

        return [
            'id' => (int) $entry->id,
            'title' => (string) $entry->title,
            'summary' => (string) ($entry->summary ?? ''),
            'content' => (string) $entry->content,
            'details' => $entry->details,
            'project' => $entry->project,
            'theme' => $entry->theme,
            'scope' => $entry->scope?->value,
            'tags' => $entry->tags ?? [],
            'importance' => (int) $entry->importance,
        ];
    }
}
