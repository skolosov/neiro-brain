<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Conversation\Pages;

use App\Enums\ConversationStatus;
use App\MoonShine\Resources\Conversation\ConversationResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<ConversationResource>
 */
class ConversationFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return [
            Box::make('Диалог', [
                ID::make()->readonly(),
                Text::make('Заголовок', 'title'),
                Text::make('Проект', 'project'),
                Text::make('Харнесс', 'harness')->readonly(),
                Enum::make('Статус', 'status')
                    ->attach(ConversationStatus::class)
                    ->required(),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string'],
        ];
    }
}
