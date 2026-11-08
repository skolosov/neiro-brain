<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\KnowledgeEntry\Pages;

use App\Enums\EntryStatus;
use App\Enums\KnowledgeKind;
use App\MoonShine\Resources\KnowledgeEntry\KnowledgeEntryResource;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<KnowledgeEntryResource>
 */
class KnowledgeEntryIndexPage extends IndexPage
{
    protected bool $isLazy = false;

    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Заголовок', 'title'),
            Enum::make('Тип', 'kind')
                ->attach(KnowledgeKind::class)
                ->badge(),
            Enum::make('Статус', 'status')
                ->attach(EntryStatus::class)
                ->badge(),
            Text::make('Проект', 'project'),
            Number::make('Важность', 'importance')->sortable(),
            Text::make('Обновлено', 'updated_at')->sortable(),
        ];
    }

    protected function filters(): iterable
    {
        return [
            Enum::make('Тип', 'kind')
                ->attach(KnowledgeKind::class)
                ->nullable(),
            Enum::make('Статус', 'status')
                ->attach(EntryStatus::class)
                ->nullable(),
            Text::make('Проект', 'project')->nullable(),
        ];
    }
}
