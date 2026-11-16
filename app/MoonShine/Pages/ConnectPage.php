<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Support\PanelAccess;
use Illuminate\Contracts\Support\Renderable;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\FlexibleRender;

/**
 * Подключение харнесса: выпуск токена и готовый конфиг.
 *
 * Сюда ведёт ссылка `/connect`, которую печатает `/brain init`. Смысл страницы
 * в том, что токен не проходит через агента: человек открывает её в браузере,
 * жмёт «Выпустить» и копирует готовый блок целиком.
 */
#[Icon('link')]
#[Group('Доступ', 'key')]
#[Order(10)]
class ConnectPage extends Page
{
    public function getTitle(): string
    {
        return 'Подключение';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return ['#' => $this->getTitle()];
    }

    /**
     * @return iterable<Renderable|string>
     */
    protected function components(): iterable
    {
        $plain = session('connect_plain');
        $name = session('connect_name');

        return [
            FlexibleRender::make(fn (): string => view('moonshine.brain.connect', [
                'plain' => is_string($plain) ? $plain : null,
                'tokenName' => is_string($name) ? $name : '',
                'canIssue' => PanelAccess::ownerId() !== null,
            ])->render()),
        ];
    }
}
