<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Conversation\Pages;

use App\MoonShine\Resources\Conversation\ConversationResource;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * A conversation is working material for summarization, not an archive: the
 * page keeps the technical facts and the transcript only while it exists.
 * The cards that came out of the dialogue are on the "Поиск" page.
 *
 * @extends DetailPage<ConversationResource>
 */
class ConversationDetailPage extends DetailPage
{
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Заголовок', 'title'),
            Text::make('Харнесс', 'harness'),
            Text::make('Проект', 'project'),
            Text::make('Статус', 'status'),
            Text::make('Сообщений', 'messages_count'),
            Date::make('Начало', 'started_at'),
            Date::make('Активность', 'last_activity_at'),
            Text::make('Ошибка', 'summary_error'),
            Textarea::make('Транскрипт', 'transcript_text')->readonly(),
        ];
    }
}
