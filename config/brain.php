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
        'proxy' => env('BRAIN_PROXY_TOKEN', 'dev-proxy-token'),
        'mcp' => env('BRAIN_MCP_TOKEN', 'dev-mcp-token'),
        'api' => env('BRAIN_API_TOKEN', 'dev-api-token'),
    ],

    'proxy' => [
        'user_agent' => env('BRAIN_PROXY_USER_AGENT', 'NeiroBrain/1.0'),
    ],

    'demo' => [
        'admin_email' => env('BRAIN_ADMIN_EMAIL', 'admin@neiro-brain.local'),
        'admin_password' => env('BRAIN_ADMIN_PASSWORD', 'password'),
    ],
];
