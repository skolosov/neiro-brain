<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Models\KnowledgeEntry;
use App\Settings\RetrievalSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds the BM25 inverted index for a knowledge entry.
 *
 * Each entry is split into chunks. Every chunk stores its term frequencies in
 * knowledge_terms, which gives us document frequency (df) and document length
 * (dl) at query time without a dedicated full-text engine.
 */
final class Indexer
{
    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly Chunker $chunker,
        private readonly RetrievalSettings $settings,
    ) {}

    public function index(KnowledgeEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $entry->chunks()->delete();

            $text = $this->textFor($entry);
            $chunks = $this->chunker->chunk(
                $text,
                $this->settings->chunk_chars,
                $this->settings->chunk_overlap,
            );

            $totalTerms = 0;
            $totalTokens = 0;

            foreach ($chunks as $position => $content) {
                $frequencies = $this->tokenizer->termFrequencies($content);
                $tokenCount = Text::estimateTokens($content);

                $chunk = $entry->chunks()->create([
                    'position' => $position,
                    'content' => $content,
                    'content_hash' => hash('sha256', $content),
                    'token_count' => $tokenCount,
                    'terms_count' => count($frequencies),
                ]);

                $rows = [];

                foreach ($frequencies as $term => $frequency) {
                    $rows[] = [
                        'chunk_id' => $chunk->id,
                        'term' => mb_substr((string) $term, 0, 64),
                        'tf' => min(65535, $frequency),
                    ];
                }

                foreach (array_chunk($rows, 500) as $batch) {
                    DB::table('knowledge_terms')->insert($batch);
                }

                $totalTerms += count($frequencies);
                $totalTokens += $tokenCount;
            }

            KnowledgeEntry::query()
                ->whereKey($entry->getKey())
                ->update([
                    'indexed_at' => now(),
                    'terms_count' => $totalTerms,
                    'tokens_count' => $totalTokens,
                ]);
        });

        Cache::forget('brain:corpus:stats');
    }

    public function remove(KnowledgeEntry $entry): void
    {
        $entry->chunks()->delete();
        Cache::forget('brain:corpus:stats');
    }

    public function indexAll(int $chunkSize = 200): int
    {
        $count = 0;

        KnowledgeEntry::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($entries) use (&$count): void {
                foreach ($entries as $entry) {
                    $this->index($entry);
                    $count++;
                }
            });

        return $count;
    }

    public function textFor(KnowledgeEntry $entry): string
    {
        $parts = [
            $entry->title,
            $entry->summary,
            $entry->project,
            $entry->theme,
            $entry->content,
            $entry->details,
            implode(', ', $entry->tags ?? []),
        ];

        return collect($parts)
            ->filter(fn (?string $part): bool => $part !== null && trim($part) !== '')
            ->implode("\n");
    }
}
