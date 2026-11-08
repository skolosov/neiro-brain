<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SearchLog\Pages;

use App\MoonShine\Resources\SearchLog\SearchLogResource;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<SearchLogResource>
 */
class SearchLogIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Запрос', 'query'),
            Text::make('Источник', 'source'),
            Number::make('Результатов', 'results_count'),
            Number::make('Мс', 'latency_ms'),
            Text::make('Когда', 'created_at')->sortable(),
        ];
    }
}
