<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

class ProxySettings extends Settings
{
    public bool $enabled = true;

    /**
     * mock | openai | deepseek | openrouter | ollama | anthropic
     */
    public string $upstream = 'mock';

    public string $base_url = '';

    #[ShouldBeEncrypted]
    public string $api_key = '';

    #[ShouldBeEncrypted]
    public string $access_token = 'dev-proxy-token';

    public string $model = 'deepseek-flash';

    public bool $inject_memory = true;

    public int $top_k = 5;

    public int $max_context_chars = 4000;

    public bool $capture = true;

    public int $timeout = 120;

    public static function group(): string
    {
        return 'proxy';
    }
}
