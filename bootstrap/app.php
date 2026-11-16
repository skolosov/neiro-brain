<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyBrainToken;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'brain.token' => VerifyBrainToken::class,
        ]);

        // Прокси обязан передавать тело байт в байт: это чужая переписка, а не
        // поля формы. Глобальные преобразования Laravel ломают её — пустая
        // строка превращается в null, а пробелы по краям срезаются, после чего
        // строгий шлюз отвечает «invalid type: null, expected a string».
        $isProxy = static fn (Request $request): bool => $request->is('v1/*')
            || ($request->is('*/v1/*') && ! $request->is('api/*'));

        $middleware->convertEmptyStringsToNull([$isProxy]);
        $middleware->trimStrings([$isProxy]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('v1/*') || $request->expectsJson(),
        );

        // Незалогиненный человек, пришедший на страницу согласия OAuth, должен
        // попасть на вход в панель и после входа вернуться к согласию: у проекта
        // один вход — /admin/login. `guest()` запоминает адрес в url.intended,
        // MoonShine после входа редиректит именно туда.
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('oauth/authorize')) {
                return null;
            }

            // Вошёл, но за входом нет человека `users` (служебная учётка
            // администратора): выдавать OAuth-доступ не от кого, и редирект на
            // вход превратился бы в петлю.
            if (Auth::guard('moonshine')->check()) {
                abort(403, 'Этот вход в панель не привязан к человеку — OAuth-доступ выдавать некому. Обратитесь к администратору.');
            }

            return redirect()->guest('/admin/login');
        });

        // A stale login form (for example after `migrate:fresh` or an
        // OpenServer restart) should return the user to the login page
        // instead of a bare "419 Page Expired" screen.
        $exceptions->render(function (Throwable $exception, Request $request) {
            $isCsrfFailure = $exception instanceof TokenMismatchException
                || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 419);

            if (! $isCsrfFailure) {
                return null;
            }

            if ($request->is('admin') || $request->is('admin/*')) {
                return redirect('/admin/login')->withErrors([
                    'username' => 'Сессия истекла. Обновите страницу и войдите снова.',
                ]);
            }

            if ($request->is('api/*') || $request->is('v1/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'type' => 'session_expired',
                        'message' => 'CSRF token or session expired. Reload the page and try again.',
                    ],
                ], 419);
            }

            return null;
        });
    })->create();
