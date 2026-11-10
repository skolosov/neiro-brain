<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Bearer token
    |--------------------------------------------------------------------------
    |
    | Один токен на все входы: прокси (/v1/*), MCP (/mcp/knowledge) и API
    | (/api/v1/*) проверяют одно и то же значение. Хранится он в настройках
    | группы security, а это значение — только начальное: им заполняется
    | пустая настройка, после чего рулит админка (`brain:token --generate`).
    |
    */
    'tokens' => [
        'all' => env('BRAIN_TOKEN', 'dev-brain-token'),

        // Значение, которое лежит в этом репозитории открытым текстом. Годится
        // только для localhost: если сервис слушает интернет, по нему сможет
        // войти любой, кто прочитал исходники. VerifyBrainToken отбрасывает
        // его для внешних запросов.
        'defaults' => ['dev-brain-token'],
    ],

    'proxy' => [
        // true — прокси пускает по одному ключу провайдера (sk-…) без токена
        // Neiro Brain. По умолчанию выключено: токен берётся из адреса или заголовка.
        'open' => env('BRAIN_PROXY_OPEN', false),
        'user_agent' => env('BRAIN_PROXY_USER_AGENT', 'NeiroBrain/1.0'),
        'upstream' => env('BRAIN_PROXY_UPSTREAM', 'mock'),
        'base_url' => env('BRAIN_PROXY_BASE_URL', ''),
    ],

    'demo' => [
        'admin_email' => env('BRAIN_ADMIN_EMAIL', 'admin@neiro-brain.local'),
        'admin_password' => env('BRAIN_ADMIN_PASSWORD', 'password'),
    ],
];
