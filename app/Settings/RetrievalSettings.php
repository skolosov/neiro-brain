<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class RetrievalSettings extends Settings
{
    public float $k1 = 1.2;

    public float $b = 0.75;

    public int $top_k = 8;

    public float $min_score = 0.01;

    /** Minimum share of query terms a chunk must contain to be considered. */
    public float $min_coverage = 0.3;

    public float $freshness_weight = 0.15;

    public float $freshness_half_life_days = 90;

    public float $importance_weight = 0.08;

    public int $chunk_chars = 900;

    public int $chunk_overlap = 120;

    public int $candidate_limit = 20000;

    public bool $include_drafts = false;

    public bool $log_searches = true;

    /** @var array<string, float> */
    public array $kind_weights = [
        'profile' => 1.25,
        'preference' => 1.15,
        'decision' => 1.10,
        'project' => 1.05,
        'stack' => 1.05,
        'style' => 1.05,
        'company' => 1.0,
        'process' => 1.0,
        'person' => 1.0,
        'fact' => 1.0,
        'other' => 0.95,
    ];

    public static function group(): string
    {
        return 'retrieval';
    }
}
