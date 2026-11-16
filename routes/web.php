<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BrainAdminController;
use App\Http\Controllers\SkillController;
use App\Http\Middleware\SetOwnerFromPanel;
use Illuminate\Support\Facades\Route;
use MoonShine\Laravel\Http\Middleware\Authenticate;

Route::get('/', function () {
    return view('home');
})->name('home');

/*
|--------------------------------------------------------------------------
| Скилл /brain и его установка
|--------------------------------------------------------------------------
|
| Публично, потому что иначе первый шаг невыполним: пока скилл не стоит,
| человеку негде узнать про панель и токен. Секрета в файле нет, знания
| по-прежнему закрыты токеном на /mcp/knowledge.
|
*/

Route::get('/skill/brain', [SkillController::class, 'skill'])->name('skill.show');
Route::get('/skill/install.ps1', [SkillController::class, 'installPs1'])->name('skill.install.ps1');
Route::get('/skill/install.sh', [SkillController::class, 'installSh'])->name('skill.install.sh');

/*
|--------------------------------------------------------------------------
| Вход один — в панель MoonShine
|--------------------------------------------------------------------------
|
| Отдельного кабинета нет: люди входят в /admin/login. Старый адрес /login
| остаётся редиректом, потому что ссылка на него уже разошлась по закладкам
| и документам. Регистрации нет: человека заводит brain:user.
|
*/

Route::redirect('/login', '/admin/login')->name('login');

Route::prefix('admin/brain')
    ->name('brain.admin.')
    ->middleware([Authenticate::class, SetOwnerFromPanel::class])
    ->group(function (): void {
        Route::post('/reindex', [BrainAdminController::class, 'reindex'])->name('reindex');
        Route::post('/settings', [BrainAdminController::class, 'updateSettings'])->name('settings');
    });
