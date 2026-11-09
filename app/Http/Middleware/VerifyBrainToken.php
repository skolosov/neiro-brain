<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\ProxySettings;
use App\Settings\SecuritySettings;
use App\Settings\SettingsInitializer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Bearer-token guard for the proxy, MCP and ingestion API.
 *
 * The proxy accepts the token in either Authorization or x-api-key because
 * Anthropic-compatible clients send the latter.
 *
 * Besides the per-scope token, a single BRAIN_TOKEN (config brain.tokens.all)
 * is accepted in every scope, so one value can be used for the proxy, MCP and
 * the API at once.
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

        $expected = $this->expectedTokens($scope);

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

    /**
     * Tokens accepted for a scope: the unified BRAIN_TOKEN first, then the
     * scope's own token.
     *
     * @return list<string>
     */
    private function expectedTokens(string $scope): array
    {
        $tokens = [];

        $unified = trim((string) config('brain.tokens.all', ''));

        if ($unified !== '') {
            $tokens[] = $unified;
        }

        $scopeToken = null;

        try {
            app(SettingsInitializer::class)->ensure();
            $scopeToken = match ($scope) {
                'proxy' => app(ProxySettings::class)->access_token,
                'mcp' => app(SecuritySettings::class)->mcp_token,
                default => app(SecuritySettings::class)->api_token,
            };
        } catch (Throwable) {
            $scopeToken = null;
        }

        $scopeToken = is_string($scopeToken) ? trim($scopeToken) : '';

        if ($scopeToken === '') {
            $fallback = config('brain.tokens.'.$scope);
            $scopeToken = is_string($fallback) ? trim($fallback) : '';
        }

        if ($scopeToken !== '') {
            $tokens[] = $scopeToken;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param  list<string>  $expected
     */
    private function matches(array $expected, string $provided): bool
    {
        foreach ($expected as $token) {
            if (hash_equals($token, $provided)) {
                return true;
            }
        }

        return false;
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
