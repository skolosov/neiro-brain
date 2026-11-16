<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiToken\Pages;

use App\MoonShine\Resources\ApiToken\ApiTokenResource;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ApiTokenResource>
 */
class ApiTokenIndexPage extends IndexPage
{
    protected bool $isLazy = false;

    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Пользователь', 'user', formatted: static fn ($user): string => (string) $user->name)
                ->badge(),
            Text::make('Имя токена', 'name'),
            Text::make('Способности', 'abilities_text'),
            Date::make('Последнее использование', 'last_used_at')->format('d.m.Y H:i'),
            Date::make('Истекает', 'expires_at')->format('d.m.Y H:i'),
            Date::make('Выпущен', 'created_at')->format('d.m.Y H:i')->sortable(),
        ];
    }

    /**
     * Стандартное удаление строки и есть отзыв: токен хранится только хешем,
     * и после удаления он мгновенно перестаёт действовать.
     */
    protected function modifyDeleteButton(ActionButtonContract $button): ActionButtonContract
    {
        return $button->setLabel('Отозвать');
    }
}
