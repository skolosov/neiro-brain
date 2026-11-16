<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use App\Support\OwnerContext;
use App\Support\OwnerResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Личный токен на входе в MCP и HTTP API.
 *
 * Токен приходит только в заголовке: `Authorization: Bearer …` или
 * `x-api-key`. Токен в адресе и `X-Neiro-Proxy-Token` больше не принимаются —
 * прокси вырезан, а личность должна быть видна явно.
 *
 * `brain.token:mcp` требует способность write (MCP умеет писать),
 * `brain.token:read` — read, `brain.token:write` — write. Право write
 * подразумевает read.
 *
 * Дев-токен из `config('brain.tokens.dev')` — единственный обходной путь: он
 * работает только при `APP_ENV=local` и только с localhost. Владельцем
 * становится первый активный пользователь.
 */
class VerifyBrainToken
{
    public function handle(Request $request, Closure $next, string $scope = 'read'): Response
    {
        $required = match ($scope) {
            'mcp', 'write' => 'write',
            default => 'read',
        };

        $plain = $this->providedToken($request);

        if ($plain === '') {
            return $this->unauthorized(
                'Токен не передан. Добавьте личный токен в заголовок Authorization: Bearer … или x-api-key.',
            );
        }

        if ($this->isDevToken($plain)) {
            if (! $this->devAllowed($request)) {
                return $this->unauthorized(
                    'Дев-токен работает только на localhost при APP_ENV=local. Выпустите личный токен в панели.',
                );
            }

            $owner = app(OwnerResolver::class)->resolve();

            if ($owner === null) {
                return $this->ownerUnavailable();
            }

            return $this->proceed($request, $next, $owner);
        }

        $token = ApiToken::query()
            ->with('user')
            ->where('token_hash', TokenIssuer::hash($plain))
            ->first();

        if ($token === null) {
            return $this->unauthorized(
                'Токен не подошёл. Проверьте значение или выпустите новый в панели: /admin, раздел «Токены».',
            );
        }

        if ($token->isExpired()) {
            return $this->unauthorized(
                'Срок действия токена истёк. Выпустите новый в панели: /admin, раздел «Токены».',
            );
        }

        $owner = $token->user;

        if ($owner === null || ! $owner->is_active) {
            return $this->unauthorized(
                'Пользователь отключён — токен больше не действует. Обратитесь к администратору.',
            );
        }

        if (! $token->hasAbility($required)) {
            return response()->json([
                'error' => [
                    'type' => 'authorization_error',
                    'code' => 'insufficient_ability',
                    'message' => $required === 'write'
                        ? 'У токена нет права write: этим токеном можно только читать. Выпустите токен с правом записи в панели.'
                        : 'У токена нет права read.',
                ],
            ], 403);
        }

        $token->touchLastUsed();

        return $this->proceed($request, $next, $owner);
    }

    /**
     * Токен — только из заголовков. Bearer приоритетнее x-api-key, потому что
     * его шлют MCP-клиенты и обычный curl.
     */
    private function providedToken(Request $request): string
    {
        $bearer = trim((string) $request->bearerToken());

        if ($bearer !== '') {
            return $bearer;
        }

        return trim((string) $request->header('x-api-key'));
    }

    private function proceed(Request $request, Closure $next, User $owner): Response
    {
        app(OwnerContext::class)->set($owner);

        return $next($request);
    }

    private function isDevToken(string $plain): bool
    {
        $dev = trim((string) config('brain.tokens.dev', ''));

        return $dev !== '' && hash_equals($dev, $plain);
    }

    /**
     * Дев-токен — значение из репозитория, поэтому он годится только для
     * локальной машины. Вне localhost это обычный неверный токен.
     */
    private function devAllowed(Request $request): bool
    {
        return app()->environment('local')
            && in_array($request->ip(), ['127.0.0.1', '::1'], true);
    }

    private function ownerUnavailable(): Response
    {
        return response()->json([
            'error' => [
                'type' => 'owner_unavailable',
                'code' => 'no_owner',
                'message' => 'В базе нет активного пользователя — владельца знаний. '
                    .'Создайте пользователя: php artisan brain:user --email=... --name=... --password=...',
            ],
        ], 503);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'error' => [
                'type' => 'authentication_error',
                'code' => 'invalid_token',
                'message' => $message,
            ],
        ], 401);
    }
}
