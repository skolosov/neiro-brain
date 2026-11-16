<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesOwnerOption;
use App\Services\Brain\Indexer;
use App\Support\OwnerContext;
use App\Support\OwnerResolver;
use Illuminate\Console\Command;

class BrainReindexCommand extends Command
{
    use ResolvesOwnerOption;

    protected $signature = 'brain:reindex
        {--user= : Владелец знаний: id или email (обязательно)}';

    protected $description = 'Rebuild the BM25 index for every knowledge entry of one owner';

    public function handle(Indexer $indexer, OwnerContext $context, OwnerResolver $resolver): int
    {
        if (! $this->setOwnerFromOption($resolver, $context)) {
            return self::FAILURE;
        }

        $count = $indexer->indexAll();

        $this->components->info(sprintf(
            'Reindexed %d entries of %s.',
            $count,
            $context->owner()->email,
        ));

        return self::SUCCESS;
    }
}
