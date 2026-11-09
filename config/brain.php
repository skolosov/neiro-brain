<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Fallback bearer tokens
    |--------------------------------------------------------------------------
    |
    | Tokens can be managed in the admin panel (they are stored encrypted).
    | These values are used before the settings table exists and as a fallback.
    |
    */
    'tokens' => [
        // Единый токен на все входы: прокси, MCP и API. Если задан, принимается
        // в любом скоупе, поэтому отдельные BRAIN_*_TOKEN нужны только если
        // хочется держать разные значения.
        'all' => env('BRAIN_TOKEN'),
        'proxy' => env('BRAIN_PROXY_TOKEN', 'dev-proxy-token'),
        'mcp' => env('BRAIN_MCP_TOKEN', 'dev-mcp-token'),
        'api' => env('BRAIN_API_TOKEN', 'dev-api-token'),
    ],

    'proxy' => [
        // true — прокси принимает ключ харнесса как ключ провайдера и не требует
        // токена Neiro Brain; ключ не сохраняется, а уходит upstream.
        'open' => env('BRAIN_PROXY_OPEN', true),
        'user_agent' => env('BRAIN_PROXY_USER_AGENT', 'NeiroBrain/1.0'),
        'upstream' => env('BRAIN_PROXY_UPSTREAM', 'mock'),
        'base_url' => env('BRAIN_PROXY_BASE_URL', ''),
    ],

    'demo' => [
        'admin_email' => env('BRAIN_ADMIN_EMAIL', 'admin@neiro-brain.local'),
        'admin_password' => env('BRAIN_ADMIN_PASSWORD', 'password'),
    ],
];
