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
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Guards\TokenGuard;
use Symfony\Component\HttpFoundation\Response;

/**
 * Вход в MCP и HTTP API: OAuth-токен Passport или личный токен `nb-…`.
 *
 * Токен приходит только в заголовке: `Authorization: Bearer …` или `x-api-key`.
 * Токен в адресе и `X-Neiro-Proxy-Token` больше не принимаются — прокси вырезан,
 * а личность должна быть видна явно.
 *
 * Сначала пробуем Passport: клиент с OAuth приходит с JWT в `Authorization` и
 * обязан иметь область `mcp:use`. Владельцем становится `token->user` — та же
 * личность `users`, что и у личного токена. Если это не JWT (`nb-…` и прочее),
 * падаем на прежнюю проверку таблицы `api_tokens`: API, CLI и клиенты без OAuth
 * работают как раньше.
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

        // Токен запоминаем до Passport: гард чистит заголовок Authorization,
        // если токен не подошёл, и повторная проверка `nb-…` иначе не увидит его.
        $plain = $this->providedToken($request);

        if ($plain === '') {
            return $this->unauthorized(
                'Токен не передан. Добавьте личный токен в заголовок Authorization: Bearer … или x-api-key '
                .'либо дайте клиенту подключиться по OAuth.',
            );
        }

        $oauthOwner = $this->oauthOwner($request);

        if ($oauthOwner !== null) {
            return $this->handleOauthToken($request, $next, $oauthOwner);
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
                'Токен не подошёл. Проверьте значение или выпустите новый в панели: /admin, раздел «Токены». '
                .'Клиент с OAuth подключается по адресу — без токена в конфиге.',
            );
        }

        if ($token->isExpired()) {
            return $this->unauthorized(
                'Срок действия токена истёк. Выпустите новый в панели: /admin, раздел «Токены».',
            );
        }

        $owner = $token->user;

        if ($owner === null || ! $owner->is_active) {
            return $this->disabledUser();
        }

        if (! $token->hasAbility($required)) {
            return $this->insufficientAbility($required);
        }

        $token->touchLastUsed();

        return $this->proceed($request, $next, $owner);
    }

    /**
     * OAuth-токен уже проверен Passport (подпись, срок, отзыв). Остаётся
     * личность, её активность и область доступа.
     */
    private function handleOauthToken(Request $request, Closure $next, User $owner): Response
    {
        if (! $owner->is_active) {
            return $this->disabledUser();
        }

        if (! $owner->tokenCan(Registrar::OAUTH_SCOPE)) {
            return $this->insufficientScope();
        }

        return $this->proceed($request, $next, $owner);
    }

    /**
     * Владелец из OAuth-токена Passport.
     *
     * Пробуем гард `api` только когда в `Authorization` лежит JWT: личные
     * токены — это `nb-…` без точек, и гонять их через Passport незачем (он бы
     * ещё и писал warning в лог на каждый запрос). Cookie-аутентификацию тоже
     * не пускаем: MCP обязан ходить с явным Bearer.
     */
    private function oauthOwner(Request $request): ?User
    {
        if (config('auth.guards.api.driver') !== 'passport') {
            return null;
        }

        if (! $this->looksLikePassportToken(trim((string) $request->bearerToken()))) {
            return null;
        }

        $guard = Auth::guard('api');

        if (! $guard instanceof TokenGuard) {
            return null;
        }

        // Гард живёт в контейнере и кэширует и запрос, и пользователя. В
        // долгоживущем процессе (тесты, Octane) это протащило бы личность
        // прошлого запроса, поэтому проверяем токен заново — именно этого.
        $guard->setRequest($request)->forgetUser();

        $user = $guard->user();

        return $user instanceof User ? $user : null;
    }

    private function looksLikePassportToken(string $plain): bool
    {
        return $plain !== ''
            && ! str_starts_with($plain, TokenIssuer::PREFIX)
            && substr_count($plain, '.') === 2;
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

    private function disabledUser(): Response
    {
        return $this->unauthorized(
            'Пользователь отключён — токен больше не действует. Обратитесь к администратору.',
        );
    }

    private function insufficientScope(): Response
    {
        return response()->json([
            'error' => [
                'type' => 'authorization_error',
                'code' => 'insufficient_scope',
                'message' => 'У OAuth-токена нет области mcp:use. Подключите клиент заново и подтвердите доступ '
                    .'в панели: /admin, раздел «Токены», блок «Подключённые приложения».',
            ],
        ], 403);
    }

    private function insufficientAbility(string $required): Response
    {
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
}
