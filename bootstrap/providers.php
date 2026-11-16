<?php

use App\Providers\AppServiceProvider;
use App\Providers\MoonShineServiceProvider;
use App\Providers\OAuthServiceProvider;

return [
    AppServiceProvider::class,
    OAuthServiceProvider::class,
    MoonShineServiceProvider::class,
];
