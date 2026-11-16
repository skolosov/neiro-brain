<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiToken;

use App\Models\ApiToken;
use App\MoonShine\Resources\ApiToken\Pages\ApiTokenIndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * Все токены людей. Только просмотр и отзыв.
 *
 * Выпуск — личное дело человека в кабинете: админ не должен уметь выписать
 * токен другому. Здесь видно, у кого есть доступ, когда токен последний раз
 * использовался и не истёк ли он.
 *
 * @extends ModelResource<ApiToken, ApiTokenIndexPage, null, null>
 */
#[Icon('key')]
#[Group('Люди', 'users')]
#[Order(21)]
class ApiTokenResource extends ModelResource
{
    protected string $model = ApiToken::class;

    protected string $title = 'Токены';

    protected string $column = 'name';

    protected bool $simplePaginate = true;

    /** @var list<string> */
    protected array $with = ['user'];

    public function getTitle(): string
    {
        return 'Токены';
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->only(Action::DELETE);
    }

    protected function pages(): array
    {
        return [
            ApiTokenIndexPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'name'];
    }
}
