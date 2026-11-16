<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Резервный выбор владельца знаний.
 *
 * Обычный HTTP-запрос получает владельца из личного токена (VerifyBrainToken),
 * а этот резолвер нужен там, где токена нет: дев-режим с localhost, локальный
 * stdio-MCP и команды, которые ещё не передали владельца явно. Берётся первый
 * активный пользователь.
 */
final class OwnerResolver
{
    /** Владелец по умолчанию: первый активный пользователь. */
    public function resolve(): ?User
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Найти владельца по id или email — для команд с обязательным --user.
     * Явно указанный владелец может быть и неактивным: переиндексировать или
     * посмотреть статистику старого пользователя иногда нужно.
     */
    public function find(string $handle): ?User
    {
        $handle = trim($handle);

        if ($handle === '') {
            return null;
        }

        if (ctype_digit($handle)) {
            $user = User::query()->find((int) $handle);

            if ($user !== null) {
                return $user;
            }
        }

        return User::query()->where('email', $handle)->first();
    }
}
