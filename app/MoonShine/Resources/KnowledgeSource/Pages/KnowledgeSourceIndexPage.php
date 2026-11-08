<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\KnowledgeSource\Pages;

use App\Enums\SourceType;
use App\MoonShine\Resources\KnowledgeSource\KnowledgeSourceResource;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<KnowledgeSourceResource>
 */
class KnowledgeSourceIndexPage extends IndexPage
{
    protected bool $isLazy = false;

    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Название', 'name'),
            Text::make('Slug', 'slug'),
            Enum::make('Тип', 'type')
                ->attach(SourceType::class)
                ->badge(),
            Switcher::make('Включён', 'enabled'),
            Text::make('Последняя синхронизация', 'last_synced_at')->sortable(),
        ];
    }
}
