<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Решает, кто владелец знаний у входящего запроса.
 *
 * Пока у инстанса один владелец: первый активный пользователь. Токены, роли и
 * вход в кабинет появятся в пассе 3 — тогда поменяется только этот класс,
 * а места вызова (middleware, команды, MCP-сервер) останутся прежними.
 */
final class OwnerResolver
{
    /** Владелец по умолчанию до пасса 3: первый активный пользователь. */
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
