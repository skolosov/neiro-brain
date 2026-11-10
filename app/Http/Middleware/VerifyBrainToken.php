<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\SecuritySettings;
use App\Settings\SettingsInitializer;
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
            return $next($request);
        }

        if ($provided !== '' && $this->matches($expected, $provided)) {
            return $next($request);
        }

        // Прозрачный прокси: вместо токена Neiro Brain клиент прислал ключ
        // провайдера (sk-…), сервер его не хранит, а передаёт upstream.
        if ($scope === 'proxy' && (bool) config('brain.proxy.open', false) && $this->looksLikeProviderKey($provided)) {
            return $next($request);
        }

        return $this->unauthorized($request, $scope);
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

    private function unauthorized(Request $request, string $scope): Response
    {
        if ($scope === 'proxy' && $request->is('v1/messages')) {
            return response()->json([
                'type' => 'error',
                'error' => [
                    'type' => 'authentication_error',
                    'message' => 'Invalid Neiro Brain proxy token.',
                ],
            ], 401);
        }

        return response()->json([
            'error' => [
                'type' => 'authentication_error',
                'code' => 'invalid_token',
                'message' => 'Invalid Neiro Brain bearer token.',
            ],
        ], 401);
    }
}
