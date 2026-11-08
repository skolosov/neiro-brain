<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Brain\Indexer;
use Illuminate\Console\Command;

class BrainReindexCommand extends Command
{
    protected $signature = 'brain:reindex';

    protected $description = 'Rebuild the BM25 index for every knowledge entry';

    public function handle(Indexer $indexer): int
    {
        $count = $indexer->indexAll();

        $this->components->info(sprintf('Reindexed %d entries.', $count));

        return self::SUCCESS;
    }
}
