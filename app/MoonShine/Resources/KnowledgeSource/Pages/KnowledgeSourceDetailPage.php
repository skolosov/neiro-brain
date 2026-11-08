<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\KnowledgeSource\Pages;

use App\MoonShine\Resources\KnowledgeSource\KnowledgeSourceResource;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Text;

/**
 * @extends DetailPage<KnowledgeSourceResource>
 */
class KnowledgeSourceDetailPage extends DetailPage
{
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Название', 'name'),
            Text::make('Slug', 'slug'),
            Text::make('Тип', 'type'),
            Text::make('Включён', 'enabled'),
            Date::make('Последняя синхронизация', 'last_synced_at'),
            Text::make('Ошибка', 'last_error'),
            Json::make('Конфигурация', 'config')->object(),
        ];
    }
}
