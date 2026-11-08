<?php

use App\Http\Controllers\Api\BrainApiController;
use App\Http\Controllers\Proxy\ProxyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Drop-in proxy (OpenAI / Anthropic compatible)
|--------------------------------------------------------------------------
|
| No prefix beyond /v1 so harnesses only need to change their base URL.
|
*/

Route::middleware('brain.token:proxy')->group(function (): void {
    Route::post('/v1/chat/completions', [ProxyController::class, 'chatCompletions']);
    Route::post('/v1/responses', [ProxyController::class, 'responses']);
    Route::post('/v1/messages', [ProxyController::class, 'messages']);
    Route::post('/v1/messages/count_tokens', [ProxyController::class, 'countTokens']);
    Route::get('/v1/models', [ProxyController::class, 'models']);
});

/*
|--------------------------------------------------------------------------
| Ingestion and search API
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1')
    ->middleware('brain.token:api')
    ->group(function (): void {
        Route::post('/ingest', [BrainApiController::class, 'ingest']);
        Route::get('/search', [BrainApiController::class, 'search']);
        Route::get('/entries/{entry}', [BrainApiController::class, 'show'])->whereNumber('entry');
        Route::post('/entries', [BrainApiController::class, 'store']);
        Route::get('/projects', [BrainApiController::class, 'projects']);
        Route::get('/stats', [BrainApiController::class, 'stats']);
    });
