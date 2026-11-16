<?php

declare(strict_types=1);

use App\Http\Middleware\SetOwnerFromAuth;
use App\Http\Middleware\VerifyBrainToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
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
            'owner.session' => SetOwnerFromAuth::class,
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
