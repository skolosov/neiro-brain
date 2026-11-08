<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Models\KnowledgeSource;

interface Connector
{
    public function supports(KnowledgeSource $source): bool;

    /**
     * @return array{conversations: int, messages: int}
     */
    public function sync(KnowledgeSource $source): array;
}
