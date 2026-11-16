<?php

use App\Http\Controllers\Api\BrainApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Knowledge API
|--------------------------------------------------------------------------
|
| The base is filled through MCP and this HTTP API: there is no provider
| proxy and no conversation ingestion any more.
|
| Чтение требует способность read, запись — write. У личного токена
| способности проверяет VerifyBrainToken, владелец берётся из токена.
|
*/

Route::prefix('api/v1')
    ->group(function (): void {
        Route::middleware('brain.token:read')->group(function (): void {
            Route::get('/search', [BrainApiController::class, 'search']);
            Route::get('/entries/{entry}', [BrainApiController::class, 'show'])->whereNumber('entry');
            Route::get('/projects', [BrainApiController::class, 'projects']);
            Route::get('/stats', [BrainApiController::class, 'stats']);
        });

        Route::middleware('brain.token:write')->group(function (): void {
            Route::post('/entries', [BrainApiController::class, 'store']);
        });
    });
