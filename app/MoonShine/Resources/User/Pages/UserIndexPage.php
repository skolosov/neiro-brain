<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\User\Pages;

use App\Models\User;
use App\MoonShine\Resources\User\UserResource;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\HttpMethod;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\FlexibleRender;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<UserResource>
 */
class UserIndexPage extends IndexPage
{
    protected bool $isLazy = false;

    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Имя', 'name'),
            Email::make('Email', 'email'),
            Text::make('Локаль', 'locale'),
            Text::make('Активен', 'is_active')
                ->modifyRawValue(fn (mixed $value): string => $value ? 'да' : 'нет'),
            Text::make('Вход в панель (moonshine_users)', 'admin_email'),
        ];
    }

    protected function filters(): iterable
    {
        return [
            Text::make('Имя', 'name')->nullable(),
            Email::make('Email', 'email')->nullable(),
        ];
    }

    protected function topLayer(): array
    {
        return [
            FlexibleRender::make(fn (): string => <<<'HTML'
                <div class="rounded-lg border border-warning bg-warning/10 px-4 py-3 text-sm">
                    Удаление человека <strong>каскадно удалит его карточки и токены</strong>.
                    Если нужно просто закрыть доступ — используйте «Отключить»: карточки останутся.
                </div>
                HTML),
            ...parent::topLayer(),
        ];
    }

    /**
     * Кнопка «Отключить» рядом с обычными действиями строки.
     */
    protected function buttons(): ListOf
    {
        return parent::buttons()->add(
            ActionButton::make('Отключить')
                ->method('disableUser')
                ->async(HttpMethod::POST)
                ->warning()
                ->icon('lock-closed')
                ->canSee(fn (mixed $item): bool => $item instanceof User && $item->is_active)
                ->showInLine(),
        );
    }

    protected function modifyDeleteButton(ActionButtonContract $button): ActionButtonContract
    {
        return $button->setLabel('Удалить');
    }
}
