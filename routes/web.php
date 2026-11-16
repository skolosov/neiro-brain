<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BrainAdminController;
use App\Http\Controllers\App\Auth\LoginController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\TokenController;
use Illuminate\Support\Facades\Route;
use MoonShine\Laravel\Http\Middleware\Authenticate;

Route::get('/', function () {
    return view('home');
})->name('home');

/*
|--------------------------------------------------------------------------
| Кабинет
|--------------------------------------------------------------------------
|
| Вход по обычному web-гарду (users, email + пароль). Регистрации нет:
| пользователя заводит админ командой brain:user. Токены — только свои.
|
*/

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.store');
Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth:web')
    ->name('logout');

Route::middleware('auth:web')
    ->prefix('app')
    ->name('app.')
    ->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::patch('/profile', [DashboardController::class, 'updateProfile'])->name('profile.update');

        Route::get('/tokens', [TokenController::class, 'index'])->name('tokens.index');
        Route::post('/tokens', [TokenController::class, 'store'])->name('tokens.store');
        Route::delete('/tokens/{token}', [TokenController::class, 'destroy'])
            ->whereNumber('token')
            ->name('tokens.destroy');
    });

Route::prefix('admin/brain')
    ->name('brain.admin.')
    ->middleware(Authenticate::class)
    ->group(function (): void {
        Route::post('/reindex', [BrainAdminController::class, 'reindex'])->name('reindex');
        Route::post('/settings', [BrainAdminController::class, 'updateSettings'])->name('settings');
    });
