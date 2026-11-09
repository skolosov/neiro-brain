<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Agents\SummaryAgent;
use App\Ai\AiProviderFactory;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Indexer;
use App\Services\Brain\Text;
use App\Settings\AiSettings;
use Illuminate\Console\Command;
use Throwable;

/**
 * Fills the one-line summary of every card that lacks one.
 *
 * The summary is what search and the model browse before committing to a full
 * card, so a card without it is nearly invisible. Run in batches: each card is
 * one small AI call, and the command can be interrupted and resumed freely.
 */
class BrainSummariesCommand extends Command
{
    protected $signature = 'brain:summaries
        {--limit=50 : How many cards to process in this run}
        {--project= : Only cards of one project}
        {--missing : Only cards without a summary (default true)}
        {--dry : Print the result without saving}';

    protected $description = 'Write the short summary of knowledge cards that lack one';

    public function handle(AiSettings $settings, AiProviderFactory $factory, Indexer $indexer): int
    {
        $provider = $factory->make($settings);

        if ($provider === null) {
            $this->components->error('AI провайдер не настроен: задайте ключ в настройках.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $project = $this->option('project') ?: null;
        $dry = (bool) $this->option('dry');

        $entries = KnowledgeEntry::query()
            ->when($project !== null, fn ($query) => $query->where('project', $project))
            ->where(fn ($query) => $query->whereNull('summary')->orWhere('summary', ''))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($entries->isEmpty()) {
            $this->components->info('Все карточки уже имеют описание.');

            return self::SUCCESS;
        }

        $done = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            $summary = $this->summaryFor($entry, $provider, $settings);

            if ($summary === null) {
                $failed++;
                $this->components->warn(sprintf('#%d: не удалось получить описание', $entry->id));

                continue;
            }

            $this->line(sprintf('#%d %s', $entry->id, $entry->title));
            $this->line(sprintf('   %s', $summary));

            if (! $dry) {
                $entry->forceFill(['summary' => $summary])->save();
                $indexer->index($entry);
            }

            $done++;
        }

        $this->components->info(sprintf('Готово: %d, ошибок: %d', $done, $failed));
        $remaining = KnowledgeEntry::query()
            ->where(fn ($query) => $query->whereNull('summary')->orWhere('summary', ''))
            ->count();

        if ($remaining > 0) {
            $this->components->warn(sprintf('Без описания осталось: %d. Запустите команду ещё раз.', $remaining));
        }

        return self::SUCCESS;
    }

    private function summaryFor(KnowledgeEntry $entry, string $provider, AiSettings $settings): ?string
    {
        $body = Text::limit(trim((string) $entry->content."\n".(string) $entry->details), 4000);

        $prompt = sprintf(
            "Проект: %s\nТема: %s\nЗаголовок: %s\n\nТекст карточки:\n%s",
            $entry->project ?? '—',
            $entry->theme ?? '—',
            $entry->title,
            $body,
        );

        try {
            $response = (new SummaryAgent)->prompt(
                $prompt,
                provider: $provider,
                model: $settings->model,
                timeout: $settings->timeout,
            );

            $summary = trim((string) ($response->toArray()['summary'] ?? ''));

            return $summary !== '' ? Text::limit($summary, 320) : null;
        } catch (Throwable $exception) {
            $this->components->warn(sprintf('#%d: %s', $entry->id, $exception->getMessage()));

            return null;
        }
    }
}
