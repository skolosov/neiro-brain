<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Support\OwnerContext;
use App\Support\OwnerResolver;

/**
 * Общая часть команд `brain:*`: обязательный `--user=` (id или email) и
 * установка владельца в контекст. Без владельца команда не работает — иначе
 * она молча считала бы по всему инстансу.
 */
trait ResolvesOwnerOption
{
    protected function setOwnerFromOption(OwnerResolver $resolver, OwnerContext $context): bool
    {
        $handle = trim((string) $this->option('user'));

        if ($handle === '') {
            $this->error('Укажите владельца знаний: --user=<id или email>.');
            $this->line('Например: php artisan '.$this->getName().' ... --user=owner@local');

            return false;
        }

        $owner = $resolver->find($handle);

        if ($owner === null) {
            $this->error('Пользователь «'.$handle.'» не найден. Укажите id или email из таблицы users.');

            return false;
        }

        $context->set($owner);

        return true;
    }
}
