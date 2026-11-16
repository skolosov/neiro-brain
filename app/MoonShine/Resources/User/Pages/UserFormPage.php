<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\User\Pages;

use App\Models\User;
use App\MoonShine\Resources\User\UserResource;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\FlexibleRender;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<UserResource, User>
 */
class UserFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return [
            Box::make('Человек', [
                ID::make()->readonly(),
                Text::make('Имя', 'name')->required(),
                Email::make('Email', 'email')->required(),
                Select::make('Локаль', 'locale')
                    ->options(['ru' => 'Русский', 'en' => 'English'])
                    ->default('ru')
                    ->required(),
                Switcher::make('Активен', 'is_active')->default(true),
                Text::make('Админка MoonShine', 'admin_email')
                    ->readonly()
                    ->hint('Связь moonshine_users.user_id. Заполняется командой brain:user --admin.'),
                Password::make('Пароль', 'password')
                    ->eye()
                    ->hint('Оставьте пустым при создании — сгенерируем и покажем один раз. При правке пустое поле не меняет пароль.'),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(User::class)->ignoreModel($item->getOriginal()),
            ],
            'locale' => ['required', 'string', Rule::in(['ru', 'en'])],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ];
    }

    /**
     * Одноразовый показ сгенерированного пароля после создания.
     */
    protected function topLayer(): array
    {
        $layers = parent::topLayer();
        $plain = session('user_password');

        if (is_string($plain) && $plain !== '') {
            $email = (string) session('user_password_email', '');
            $html = '<div class="rounded-lg border border-success bg-success/10 px-4 py-3 text-sm">'
                .'Пароль для <strong>'.e($email).'</strong>: '
                .'<code class="text-base">'.e($plain).'</code>. '
                .'Скопируйте его сейчас — второй раз он не покажется.'
                .'</div>';

            array_unshift($layers, FlexibleRender::make($html));
        }

        return $layers;
    }
}
