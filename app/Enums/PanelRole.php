<?php

declare(strict_types=1);

namespace App\Enums;

use MoonShine\Laravel\Models\MoonshineUserRole;

/**
 * Роли панели MoonShine.
 *
 * В пакете есть только таблица `moonshine_user_roles` и проверка
 * `MoonshineUser::isSuperUser()`: роль 1 — администратор, всё остальное — обычный
 * человек. Здесь роли названы явно, чтобы по коду не гуляли числа 1 и 2.
 */
enum PanelRole: int
{
    case Admin = MoonshineUserRole::DEFAULT_ROLE_ID;

    /**
     * Роль обычного человека. Она создаётся миграцией с явным id, поэтому
     * значение стабильно и не зависит от порядка вставки в базе.
     */
    case User = 2;

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Администратор',
            self::User => 'Пользователь',
        };
    }

    /**
     * Роль по id из базы. Незнакомый id считается ролью обычного человека:
     * неизвестная роль не должна давать доступ к чужим карточкам.
     */
    public static function fromId(?int $roleId): self
    {
        return self::tryFrom((int) $roleId) ?? self::User;
    }
}
