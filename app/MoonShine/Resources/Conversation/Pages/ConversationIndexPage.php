<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Conversation\Pages;

use App\Enums\ConversationStatus;
use App\MoonShine\Resources\Conversation\ConversationResource;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ConversationResource>
 */
class ConversationIndexPage extends IndexPage
{
    protected bool $isLazy = false;

    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Заголовок', 'title'),
            Text::make('Харнесс', 'harness'),
            Text::make('Проект', 'project'),
            Enum::make('Статус', 'status')
                ->attach(ConversationStatus::class)
                ->badge(),
            Number::make('Сообщений', 'messages_count'),
            Text::make('Активность', 'last_activity_at')->sortable(),
        ];
    }

    protected function filters(): iterable
    {
        return [
            Enum::make('Статус', 'status')
                ->attach(ConversationStatus::class)
                ->nullable(),
            Text::make('Проект', 'project')->nullable(),
        ];
    }
}
