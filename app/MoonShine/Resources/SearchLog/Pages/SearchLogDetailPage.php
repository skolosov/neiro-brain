<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SearchLog\Pages;

use App\MoonShine\Resources\SearchLog\SearchLogResource;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends DetailPage<SearchLogResource>
 */
class SearchLogDetailPage extends DetailPage
{
    protected function fields(): iterable
    {
        return [
            Text::make('Запрос', 'query'),
            Text::make('Нормализованный', 'normalized_query'),
            Text::make('Источник', 'source'),
            Number::make('Результатов', 'results_count'),
            Number::make('Латентность, мс', 'latency_ms'),
            Json::make('Фильтры', 'filters')->object(),
            Text::make('Когда', 'created_at'),
        ];
    }
}
