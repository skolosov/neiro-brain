<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Agents\ConsolidationAgent;
use App\Ai\AiProviderFactory;
use App\Enums\EntryStatus;
use App\Enums\KnowledgeKind;
use App\Enums\KnowledgeScope;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\Indexer;
use App\Services\Brain\KnowledgeWriter;
use App\Services\Brain\SourceRegistry;
use App\Services\Brain\Text;
use App\Settings\AiSettings;
use Illuminate\Console\Command;
use Throwable;

/**
 * Folds overlapping cards of one project into theme cards.
 *
 * Repeated extraction describes the same theme in different words, so the
 * content-hash deduplication never sees it as a duplicate. This command shows
 * the model the whole set at once and asks for a smaller set that keeps every
 * fact; the originals are then superseded, so the history stays intact.
 */
class BrainConsolidateCommand extends Command
{
    protected $signature = 'brain:consolidate
        {--project= : Consolidate cards of one project (omit to use scope)}
        {--scope= : Consolidate cards of one scope: profile, environment}
        {--batch=30 : How many cards go into one model call}
        {--dry : Show the plan without writing anything}';

    protected $description = 'Merge overlapping knowledge cards into theme cards';

    public function handle(
        AiSettings $settings,
        AiProviderFactory $factory,
        KnowledgeWriter $writer,
        Indexer $indexer,
        SourceRegistry $sources,
    ): int {
        $project = $this->option('project') ?: null;
        $scope = $this->option('scope') ?: null;

        if ($project === null && $scope === null) {
            $this->components->error('Укажите --project или --scope.');

            return self::FAILURE;
        }

        $provider = $factory->make($settings);

        if ($provider === null) {
            $this->components->error('AI провайдер не настроен: задайте ключ в настройках.');

            return self::FAILURE;
        }

        $entries = KnowledgeEntry::query()
            ->approved()
            ->when($project !== null, fn ($query) => $query->where('project', $project))
            ->when($scope !== null && $project === null, fn ($query) => $query->where('scope', $scope))
            ->orderByDesc('importance')
            ->get();

        if ($entries->count() < 2) {
            $this->components->info('Нечего сводить: карточек меньше двух.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Сводим %d карточек.', $entries->count()));

        // Keep the prompt inside the provider's context: a big project would
        // otherwise blow the window and the whole run fails with no result.
        $batch = (int) $this->option('batch');
        $contentChars = $entries->count() > $batch ? 500 : 1200;
        $groups = $entries->chunk($batch);

        $this->components->info(sprintf(
            'Пачки по %d, по %d символов на карточку.',
            $batch,
            $contentChars,
        ));

        $created = [];
        $replaced = 0;

        foreach ($groups as $groupIndex => $group) {
            $payload = $group->map(fn (KnowledgeEntry $entry): string => sprintf(
                "### %s\n%s\n%s",
                $entry->title,
                Text::limit((string) ($entry->summary ?? ''), 300),
                Text::limit((string) $entry->content, $contentChars),
            ))->implode("\n\n");

            try {
                $response = (new ConsolidationAgent)->prompt(
                    "Сведи эти карточки в меньшее число карточек по темам, сохранив все факты:\n\n".$payload,
                    provider: $provider,
                    model: $settings->model,
                    timeout: $settings->timeout,
                );

                $cards = $response->toArray()['cards'] ?? [];
            } catch (Throwable $exception) {
                $this->components->error('Модель недоступна: '.$exception->getMessage());

                if ($created === []) {
                    return self::FAILURE;
                }

                break;
            }

            if (! is_array($cards) || $cards === []) {
                $this->components->warn(sprintf('Пачка %d: пустой ответ, пропускаю.', $groupIndex + 1));

                continue;
            }

            $this->line(sprintf('Пачка %d: %d → %d', $groupIndex + 1, $group->count(), count($cards)));

            // A dry run must not touch the base at all: no new cards either.
            if ((bool) $this->option('dry')) {
                foreach ($cards as $card) {
                    $this->line(sprintf(
                        '  + %s — %s',
                        $card['title'] ?? '?',
                        Text::limit((string) ($card['summary'] ?? ''), 100),
                    ));
                }

                continue;
            }

            foreach ($cards as $card) {
                if (! is_array($card) || trim((string) ($card['title'] ?? '')) === '') {
                    continue;
                }

                $result = $writer->write(
                    new ExtractedEntry(
                        kind: KnowledgeKind::Fact,
                        title: (string) $card['title'],
                        content: (string) ($card['content'] ?? ''),
                        summary: (string) ($card['summary'] ?? ''),
                        project: $project,
                        theme: isset($card['theme']) ? (string) $card['theme'] : null,
                        scope: $scope !== null
                            ? (KnowledgeScope::tryFrom($scope) ?? KnowledgeScope::Profile)
                            : KnowledgeScope::Project,
                        tags: array_values(array_filter((array) ($card['tags'] ?? []))),
                        importance: (int) ($card['importance'] ?? 4),
                        confidence: 0.9,
                    ),
                    source: $sources->manual(),
                    status: EntryStatus::Approved,
                    metadata: ['engine' => 'consolidate'],
                );

                if ($result !== null) {
                    $created[] = ['entry' => $result['entry'], 'group' => $groupIndex];
                }
            }

            // The originals become history: search stops returning them, but the
            // superseded chain still shows what the card looked like before.
            $first = collect($created)->firstWhere('group', $groupIndex);

            foreach ($group as $entry) {
                if ($first === null) {
                    continue;
                }

                $entry->forceFill(['superseded_by' => $first['entry']->getKey()])->save();
                $indexer->index($entry);
                $replaced++;
            }
        }

        $this->components->info(sprintf(
            'Создано %d карточек, заменено %d старых.',
            count($created),
            $replaced,
        ));

        return self::SUCCESS;
    }
}
