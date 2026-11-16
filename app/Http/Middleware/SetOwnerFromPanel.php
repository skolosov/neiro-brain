<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\OwnerContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MoonShine\Laravel\Models\MoonshineUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Владелец знаний для страниц панели MoonShine.
 *
 * Панель — единственный интерфейс, поэтому контекст владельца ставится здесь:
 * `Authenticate` уже проверил вход, а `VerifyBrainToken` к панели не относится.
 *
 * Администратор панели видит всю базу, и контекст для него намеренно пуст —
 * глобальный скоуп владельца в таком случае не накладывается. Обычный человек
 * получает контекст своего `users`-человека, и дальше все выборки (карточки,
 * поиск, граф, проекты, токены) ограничены им.
 *
 * Если вход в панель не привязан к активному человеку, запрос закрывается 403:
 * иначе такой вход видел бы всю базу, потому что пустой контекст — это «всё».
 */
class SetOwnerFromPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $panelUser = Auth::guard('moonshine')->user();

        if (! $panelUser instanceof MoonshineUser) {
            return $next($request);
        }

        if ($panelUser->isSuperUser()) {
            app(OwnerContext::class)->forget();

            return $next($request);
        }

        $owner = $panelUser->user_id !== null
            ? User::query()->find($panelUser->user_id)
            : null;

        if ($owner === null || ! $owner->is_active) {
            abort(403, 'Вход в панель не привязан к активному человеку. Обратитесь к администратору.');
        }

        app(OwnerContext::class)->set($owner);

        return $next($request);
    }
}
