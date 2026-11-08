<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\KnowledgeEntry;
use App\Services\Brain\Indexer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps the BM25 index in sync with admin edits and API writes.
 */
class KnowledgeEntryObserver
{
    public function saved(KnowledgeEntry $entry): void
    {
        if (! $entry->wasRecentlyCreated && ! $entry->wasChanged([
            'title', 'content', 'details', 'tags', 'project', 'kind', 'status',
        ])) {
            return;
        }

        try {
            app(Indexer::class)->index($entry);
        } catch (Throwable $exception) {
            Log::error('Neiro Brain: failed to index knowledge entry.', [
                'entry_id' => $entry->id,
                'error' => $exception->getMessage(),
            ]);

            KnowledgeEntry::query()->whereKey($entry->getKey())->update(['indexed_at' => null]);
        }
    }
}
