<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\KnowledgeEntry\Pages;

use App\Enums\EntryStatus;
use App\Enums\KnowledgeKind;
use App\MoonShine\Resources\KnowledgeEntry\KnowledgeEntryResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends FormPage<KnowledgeEntryResource>
 */
class KnowledgeEntryFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return [
            Box::make('Основное', [
                ID::make()->readonly(),
                Text::make('Заголовок', 'title')->required(),
                Enum::make('Тип', 'kind')
                    ->attach(KnowledgeKind::class)
                    ->required(),
                Enum::make('Статус', 'status')
                    ->attach(EntryStatus::class)
                    ->required(),
                Text::make('Проект', 'project'),
                Number::make('Важность', 'importance')
                    ->min(1)
                    ->max(5)
                    ->default(3),
                Text::make('Теги (через запятую)', 'tags_text'),
            ]),
            Box::make('Содержание', [
                Textarea::make('Кратко', 'content')->required(),
                Textarea::make('Детали', 'details'),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'kind' => ['required', 'string'],
            'status' => ['required', 'string'],
            'importance' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
