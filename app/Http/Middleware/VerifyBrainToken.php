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
 */
class VerifyBrainToken
{
    public function handle(Request $request, Closure $next, string $scope = 'api'): Response
    {
        $expected = $this->expectedToken($scope);
        $proxyHeader = trim((string) $request->header('X-Neiro-Proxy-Token'));

        // Preferred for harnesses: the model provider key stays in Authorization,
        // while Neiro Brain's own token travels in X-Neiro-Proxy-Token.
        $provided = $proxyHeader !== ''
            ? $proxyHeader
            : ($request->bearerToken() ?: $request->header('x-api-key'));

        if ($expected === null || $provided === null || ! hash_equals($expected, $provided)) {
            return $this->unauthorized($request, $scope);
        }

        return $next($request);
    }

    private function expectedToken(string $scope): ?string
    {
        try {
            app(SettingsInitializer::class)->ensure();
            $token = match ($scope) {
                'proxy' => app(ProxySettings::class)->access_token,
                'mcp' => app(SecuritySettings::class)->mcp_token,
                default => app(SecuritySettings::class)->api_token,
            };
        } catch (Throwable) {
            $token = null;
        }

        $token = is_string($token) ? trim($token) : '';

        if ($token !== '') {
            return $token;
        }

        $fallback = config('brain.tokens.'.$scope);

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
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
