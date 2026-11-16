<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Личные токены
    |--------------------------------------------------------------------------
    |
    | Обычный вход — токен из таблицы api_tokens: он приходит в заголовке
    | Authorization: Bearer … или x-api-key. Токен в адресе и прокси-заголовки
    | больше не принимаются.
    |
    | `dev` — единственный обходной путь. Это значение лежит в репозитории,
    | поэтому VerifyBrainToken принимает его только при APP_ENV=local и только
    | с localhost; владельцем становится первый активный пользователь. На
    | публичном сервере оно не работает.
    |
    */
    'tokens' => [
        'dev' => env('BRAIN_DEV_TOKEN', 'dev-brain-token'),
    ],

    'demo' => [
        'admin_email' => env('BRAIN_ADMIN_EMAIL', 'admin@neiro-brain.local'),
        'admin_password' => env('BRAIN_ADMIN_PASSWORD', 'password'),
    ],
];
