<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\SecuritySettings;
use App\Settings\SettingsInitializer;
use App\Support\OwnerContext;
use App\Support\OwnerResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Bearer-token guard for the proxy, MCP and ingestion API.
 *
 * Один токен на все три входа: значение берётся из настроек группы security,
 * а если там пусто — из BRAIN_TOKEN (brain.tokens.all). Скоуп влияет только на
 * формат ответа об ошибке, а не на набор допустимых значений.
 *
 * Прокси принимает токен в Authorization или x-api-key, потому что
 * Anthropic-совместимые клиенты шлют второе.
 */
class VerifyBrainToken
{
    public function handle(Request $request, Closure $next, string $scope = 'api'): Response
    {
        $proxyHeader = trim((string) $request->header('X-Neiro-Proxy-Token'));

        // Preferred for harnesses: the model provider key stays in Authorization,
        // while Neiro Brain's own token travels in X-Neiro-Proxy-Token.
        $provided = $proxyHeader !== ''
            ? $proxyHeader
            : (string) ($request->bearerToken() ?: $request->header('x-api-key'));

        $expected = $this->expectedToken();

        // Значение из репозитория отбрасываем для всего, что пришло не с самой
        // машины: иначе публичный сервер пускает по токену, который знает
        // любой читатель исходников.
        if ($expected !== '' && ! $this->defaultsAllowed($request) && $this->isShippedDefault($expected)) {
            $expected = '';
        }

        // Токен в адресе — как в git remote: http://host/<token>/v1 или
        // basic-auth userinfo http://<token>@host/v1.
        $addressToken = trim((string) ($request->route('brainToken') ?? $request->getUser() ?? ''));

        if ($addressToken !== '' && $this->matches($expected, $addressToken)) {
            return $this->proceed($request, $next);
        }

        if ($provided !== '' && $this->matches($expected, $provided)) {
            return $this->proceed($request, $next);
        }

        // Прозрачный прокси: вместо токена Neiro Brain клиент прислал ключ
        // провайдера (sk-…), сервер его не хранит, а передаёт upstream.
        if ($scope === 'proxy' && (bool) config('brain.proxy.open', false) && $this->looksLikeProviderKey($provided)) {
            return $this->proceed($request, $next);
        }

        return $this->unauthorized($request, $scope, $addressToken !== '');
    }

    /**
     * Токен проверен — ставим владельца знаний в контекст запроса.
     *
     * Пока токен один, владелец один и берётся из OwnerResolver (первый
     * активный пользователь). Если владельца нет, отвечаем 503: без него
     * читать и записывать знания некуда, а пускать запрос «во все владельцы»
     * нельзя.
     */
    private function proceed(Request $request, Closure $next): Response
    {
        $owner = app(OwnerResolver::class)->resolve();

        if ($owner === null) {
            return response()->json([
                'error' => [
                    'type' => 'owner_unavailable',
                    'code' => 'no_owner',
                    'message' => 'В базе нет активного пользователя — владельца знаний. '
                        .'Создайте пользователя (php artisan db:seed) и повторите запрос.',
                ],
            ], 503);
        }

        app(OwnerContext::class)->set($owner);

        return $next($request);
    }

    private function looksLikeProviderKey(string $token): bool
    {
        return str_starts_with(trim($token), 'sk-');
    }

    private function isShippedDefault(string $token): bool
    {
        return in_array($token, (array) config('brain.tokens.defaults', []), true);
    }

    /**
     * Tokens committed to the repository are meant for `localhost` only. Any
     * other caller must authenticate with a value that is not in the sources.
     */
    private function defaultsAllowed(Request $request): bool
    {
        return app()->environment(['local', 'testing'])
            && in_array($request->ip(), ['127.0.0.1', '::1'], true);
    }

    /**
     * Настройка первична: её пишет админка и `brain:token --generate`.
     * Пустая настройка означает, что установка живёт на BRAIN_TOKEN.
     */
    private function expectedToken(): string
    {
        try {
            app(SettingsInitializer::class)->ensure();
            $stored = trim((string) app(SecuritySettings::class)->access_token);
        } catch (Throwable) {
            $stored = '';
        }

        if ($stored !== '') {
            return $stored;
        }

        return trim((string) config('brain.tokens.all', ''));
    }

    private function matches(string $expected, string $provided): bool
    {
        return $expected !== '' && hash_equals($expected, $provided);
    }

    /**
     * Токен в адресе — это часть пути: если он устарел, адреса не существует.
     * Отвечаем 404, а не 401, чтобы харнесс показал текст, а не свою строку
     * «Ключ API недействителен»: чинить нужно URL, а не ключ модели.
     */
    private function unauthorized(Request $request, string $scope, bool $addressToken = false): Response
    {
        $hint = 'Действующий токен покажет команда php artisan brain:token.';

        if ($addressToken) {
            $message = 'Адрес прокси с этим токеном не найден: токен в адресе устарел. '
                .'Скопируйте актуальный адрес прокси и замените префикс в базовом URL провайдера. '.$hint;

            $payload = $request->is('v1/messages')
                ? ['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => $message]]
                : ['error' => ['type' => 'invalid_request_error', 'message' => $message]];

            return response()->json($payload, 404);
        }

        if ($scope === 'proxy' && $request->is('v1/messages')) {
            return response()->json([
                'type' => 'error',
                'error' => [
                    'type' => 'authentication_error',
                    'message' => 'Токен Neiro Brain не подошёл. '.$hint,
                ],
            ], 401);
        }

        return response()->json([
            'error' => [
                'type' => 'authentication_error',
                'code' => 'invalid_token',
                'message' => 'Токен Neiro Brain не подошёл. '.$hint,
            ],
        ], 401);
    }
}
