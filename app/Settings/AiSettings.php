<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

class AiSettings extends Settings
{
    public bool $enabled = true;

    public string $provider = 'openai';

    public string $model = 'gpt-4o-mini';

    #[ShouldBeEncrypted]
    public string $api_key = '';

    public string $base_url = '';

    public float $temperature = 0.2;

    public int $max_tokens = 4000;

    public int $timeout = 120;

    /** When the provider is unavailable, fall back to the local extractor. */
    public bool $heuristic_fallback = true;

    public static function group(): string
    {
        return 'ai';
    }
}
