<?php

declare(strict_types=1);

namespace App\MoonShine\Concerns;

use App\Support\PanelAccess;
use MoonShine\Support\Enums\Ability;

/**
 * Ресурс, который открывает только администратор панели.
 *
 * Меню прячет такие пункты обычному человеку, но адрес можно ввести руками.
 * Проверка стоит в `isCan`, поэтому MoonShine закрывает 403 и сам список, и
 * создание, и удаление — независимо от того, что нарисовано в меню.
 */
trait AdminOnlyResource
{
    protected function isCan(Ability $ability): bool
    {
        return PanelAccess::isAdmin() && parent::isCan($ability);
    }
}
