<?php

declare(strict_types=1);

namespace App\Ai;

use App\Settings\AiSettings;
use Laravel\Ai\Ai;

/**
 * Builds an on-demand AI SDK provider from runtime settings.
 *
 * This lets API keys and base URLs live in the admin panel instead of .env.
 */
final class AiProviderFactory
{
    public function make(AiSettings $settings): ?string
    {
        if (! $settings->enabled || trim($settings->api_key) === '') {
            return null;
        }

        $name = 'brain_'.substr(hash('sha256', $settings->provider.microtime(true).random_int(1, PHP_INT_MAX)), 0, 16);

        $config = [
            'name' => $name,
            'driver' => $settings->provider,
            'key' => $settings->api_key,
        ];

        if (trim($settings->base_url) !== '') {
            $config['url'] = $settings->base_url;
        }

        Ai::build($config);

        return $name;
    }
}
