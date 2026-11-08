<?php

declare(strict_types=1);

namespace App\Services\Proxy;

use App\Services\Proxy\Dto\UpstreamConfig;
use App\Settings\ProxySettings;

final class UpstreamResolver
{
    /** @var array<string, string> */
    private const DEFAULT_BASE_URLS = [
        'openai' => 'https://api.openai.com/v1',
        'deepseek' => 'https://api.deepseek.com/v1',
        'openrouter' => 'https://openrouter.ai/api/v1',
        'ollama' => 'http://127.0.0.1:11434/v1',
        'anthropic' => 'https://api.anthropic.com/v1',
    ];

    /** @var array<string, string> */
    private const KEY_ENV = [
        'openai' => 'OPENAI_API_KEY',
        'deepseek' => 'DEEPSEEK_API_KEY',
        'openrouter' => 'OPENROUTER_API_KEY',
        'ollama' => 'OLLAMA_API_KEY',
        'anthropic' => 'ANTHROPIC_API_KEY',
    ];

    public function resolve(ProxySettings $settings, ?string $requestedModel = null): UpstreamConfig
    {
        $driver = strtolower(trim($settings->upstream ?: 'mock'));

        if ($driver === 'mock') {
            return new UpstreamConfig(
                driver: 'mock',
                baseUrl: '',
                apiKey: '',
                model: $requestedModel ?: $settings->model,
                timeout: $settings->timeout,
                mock: true,
            );
        }

        $baseUrl = trim($settings->base_url);

        if ($baseUrl === '') {
            $baseUrl = self::DEFAULT_BASE_URLS[$driver] ?? '';
        }

        $apiKey = trim($settings->api_key);

        if ($apiKey === '' && isset(self::KEY_ENV[$driver])) {
            $apiKey = (string) env(self::KEY_ENV[$driver], '');
        }

        return new UpstreamConfig(
            driver: $driver,
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            model: $requestedModel ?: $settings->model,
            timeout: $settings->timeout,
            mock: false,
        );
    }
}
