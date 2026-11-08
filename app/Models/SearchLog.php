<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'results_count' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    public function topEntry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeEntry::class, 'top_entry_id');
    }
}
