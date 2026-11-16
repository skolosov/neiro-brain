<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\OwnerContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Владелец знаний для страниц кабинета.
 *
 * Middleware токена ставит владельца в API и MCP, а кабинет живёт на обычной
 * сессии `web`. Без этого шага сервисы чтения не нашли бы ни одной карточки:
 * `OwnerContext` пуст, а глобальный скоуп владельца в таком случае не
 * накладывается вовсе.
 */
class SetOwnerFromAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user !== null) {
            app(OwnerContext::class)->set($user);
        }

        return $next($request);
    }
}
