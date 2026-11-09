<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BrainAdminController;
use Illuminate\Support\Facades\Route;
use MoonShine\Laravel\Http\Middleware\Authenticate;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::prefix('admin/brain')
    ->name('brain.admin.')
    ->middleware(Authenticate::class)
    ->group(function (): void {
        Route::post('/sync', [BrainAdminController::class, 'sync'])->name('sync');
        Route::post('/reindex', [BrainAdminController::class, 'reindex'])->name('reindex');
        Route::post('/conversations/{conversation}/summarize', [BrainAdminController::class, 'summarize'])->name('summarize');
        Route::post('/settings', [BrainAdminController::class, 'updateSettings'])->name('settings');
        Route::post('/tokens', [BrainAdminController::class, 'generateToken'])->name('tokens.generate');
    });
