<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiToken\Pages;

use App\MoonShine\Resources\ApiToken\ApiTokenResource;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * Выпуск личного токена из панели.
 *
 * Форма не спрашивает владельца: им становится человек, привязанный к текущему
 * входу. Это единственный способ выпустить токен — администратора здесь нет,
 * потому что у него нет своего человека в базе.
 *
 * @extends FormPage<ApiTokenResource>
 */
class ApiTokenFormPage extends FormPage
{
    public function getTitle(): string
    {
        return 'Выпустить токен';
    }

    protected function fields(): iterable
    {
        return [
            Text::make('Название', 'name')
                ->required()
                ->hint('Например: «Ноутбук» или «Рабочий сервер».'),
            Select::make('Способности', 'abilities')
                ->options([
                    'read' => 'чтение',
                    'write' => 'запись (включает чтение)',
                ])
                ->multiple()
                ->required()
                ->hint('Чтение открывает поиск и API. Запись нужна MCP и созданию карточек.'),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(['read', 'write'])],
        ];
    }
}
