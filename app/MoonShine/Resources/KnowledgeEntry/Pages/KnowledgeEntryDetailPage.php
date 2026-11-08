<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\KnowledgeEntry\Pages;

use App\MoonShine\Resources\KnowledgeEntry\KnowledgeEntryResource;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends DetailPage<KnowledgeEntryResource>
 */
class KnowledgeEntryDetailPage extends DetailPage
{
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Заголовок', 'title'),
            Text::make('Тип', 'kind'),
            Text::make('Статус', 'status'),
            Text::make('Проект', 'project'),
            Number::make('Важность', 'importance'),
            Text::make('Теги', 'tags_text'),
            Date::make('Создано', 'created_at'),
            Date::make('Обновлено', 'updated_at'),
            Textarea::make('Кратко', 'content')->readonly(),
            Textarea::make('Детали', 'details')->readonly(),
        ];
    }
}
