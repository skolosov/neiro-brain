<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use LogicException;

/**
 * Владелец знаний текущего запроса.
 *
 * Карточка всегда принадлежит пользователю, но сам пользователь никогда не
 * приходит аргументом: его кладёт сюда тот, кто обрабатывает запрос (middleware,
 * команда, тест). Сервисы читают владельца только отсюда, поэтому забытый
 * контекст — это исключение, а не запись «в никуда».
 *
 * Регистрируется как scoped, то есть живёт один запрос, работу или тест.
 */
final class OwnerContext
{
    private ?User $owner = null;

    public function set(User $user): void
    {
        $this->owner = $user;
    }

    public function forget(): void
    {
        $this->owner = null;
    }

    public function has(): bool
    {
        return $this->owner !== null;
    }

    public function owner(): User
    {
        if ($this->owner === null) {
            throw new LogicException(
                'Владелец знаний не определён: контекст запроса пуст. '
                .'Установите владельца (OwnerContext::set) до чтения или записи знаний.',
            );
        }

        return $this->owner;
    }

    public function id(): int
    {
        return (int) $this->owner()->getKey();
    }
}
