<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\ApiToken;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Выпуск личных токенов.
 *
 * Плейн-токен существует только в момент выпуска: в базу пишется SHA-256.
 * Префикс `nb-` оставлен от прежнего общего токена — клиенты и документация
 * уже знают этот формат.
 */
final class TokenIssuer
{
    public const PREFIX = 'nb-';

    public const RANDOM_LENGTH = 40;

    /**
     * @param  list<string>  $abilities
     * @return array{token: ApiToken, plain: string}
     */
    public function issue(
        User $user,
        string $name,
        array $abilities,
        ?DateTimeInterface $expiresAt = null,
    ): array {
        $plain = self::PREFIX.Str::random(self::RANDOM_LENGTH);

        $token = $user->apiTokens()->create([
            'name' => trim($name) !== '' ? trim($name) : 'Без названия',
            'token_hash' => self::hash($plain),
            'abilities' => self::normalizeAbilities($abilities),
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * @param  list<string>  $abilities
     * @return list<string>
     */
    public static function normalizeAbilities(array $abilities): array
    {
        $allowed = ['read', 'write'];
        $normalized = array_values(array_unique(array_intersect($abilities, $allowed)));

        return $normalized === [] ? ['read'] : $normalized;
    }
}
