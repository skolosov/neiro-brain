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
*/

Route::prefix('api/v1')
    ->middleware('brain.token:api')
    ->group(function (): void {
        Route::get('/search', [BrainApiController::class, 'search']);
        Route::get('/entries/{entry}', [BrainApiController::class, 'show'])->whereNumber('entry');
        Route::post('/entries', [BrainApiController::class, 'store']);
        Route::get('/projects', [BrainApiController::class, 'projects']);
        Route::get('/stats', [BrainApiController::class, 'stats']);
    });
