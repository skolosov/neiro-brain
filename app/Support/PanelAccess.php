<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use MoonShine\Laravel\Models\MoonshineUser;

/**
 * Кто смотрит панель и чей человек за этим входом.
 *
 * Интерфейс один — админка MoonShine, поэтому все проверки ролей собраны здесь,
 * а не размазаны по ресурсам и меню. Учётная запись панели (`moonshine_users`)
 * связана с человеком (`users`) через `user_id`: карточки и токены принадлежат
 * человеку, а вход в панель — его учётной записи.
 */
final class PanelAccess
{
    /**
     * Текущий вход в панель. Через `auth('moonshine')`, а не `Auth::user()`:
     * обычный web-гард к панели отношения не имеет.
     */
    private static function panelUser(): ?MoonshineUser
    {
        $user = auth('moonshine')->user();

        return $user instanceof MoonshineUser ? $user : null;
    }

    public static function isAdmin(): bool
    {
        return self::panelUser()?->isSuperUser() === true;
    }

    /**
     * id человека, к которому привязан вход в панель.
     */
    public static function ownerId(): ?int
    {
        $id = self::panelUser()?->user_id;

        return $id !== null ? (int) $id : null;
    }

    /**
     * Человек, чьи карточки и токены видит текущий вход. Админ панели ни к кому
     * не привязан: он видит всё, и «своего» человека у него нет.
     */
    public static function owner(): ?User
    {
        $id = self::ownerId();

        return $id !== null ? User::query()->find($id) : null;
    }
}
