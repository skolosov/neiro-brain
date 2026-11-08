<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\KnowledgeSource\Pages;

use App\Enums\SourceType;
use App\MoonShine\Resources\KnowledgeSource\KnowledgeSourceResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<KnowledgeSourceResource>
 */
class KnowledgeSourceFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return [
            Box::make('Источник', [
                ID::make()->readonly(),
                Text::make('Название', 'name')->required(),
                Text::make('Slug', 'slug')->required(),
                Enum::make('Тип', 'type')
                    ->attach(SourceType::class)
                    ->required(),
                Switcher::make('Включён', 'enabled')->default(true),
                Json::make('Конфигурация', 'config')->object(),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
        ];
    }
}
