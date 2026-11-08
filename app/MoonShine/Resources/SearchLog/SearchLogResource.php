<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SearchLog;

use App\Models\SearchLog;
use App\MoonShine\Resources\SearchLog\Pages\SearchLogDetailPage;
use App\MoonShine\Resources\SearchLog\Pages\SearchLogIndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * @extends ModelResource<SearchLog, SearchLogIndexPage, null, SearchLogDetailPage>
 */
#[Icon('magnifying-glass')]
#[Group('База знаний', 'book-open')]
#[Order(40)]
class SearchLogResource extends ModelResource
{
    protected string $model = SearchLog::class;

    protected string $title = 'Поисковые запросы';

    protected string $column = 'query';

    protected bool $simplePaginate = true;

    protected function pages(): array
    {
        return [
            SearchLogIndexPage::class,
            SearchLogDetailPage::class,
        ];
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::CREATE, Action::UPDATE, Action::DELETE);
    }
}
