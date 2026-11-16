<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\User;

use App\Models\User;
use App\MoonShine\Resources\User\Pages\UserFormPage;
use App\MoonShine\Resources\User\Pages\UserIndexPage;
use Illuminate\Support\Str;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Attributes\Icon;

/**
 * Люди, которые пользуются базой знаний.
 *
 * Это не админы MoonShine: у человека есть вход в кабинет и свои карточки.
 * Пароль можно задать вручную, а если оставить поле пустым при создании —
 * сгенерируем и покажем один раз. Токены здесь не выпускаются: это делает
 * сам человек в кабинете.
 *
 * @extends ModelResource<User, UserIndexPage, UserFormPage, null>
 */
#[Icon('users')]
#[Group('Люди', 'users')]
#[Order(20)]
class UserResource extends ModelResource
{
    protected string $model = User::class;

    protected string $title = 'Люди';

    protected string $column = 'name';

    protected bool $simplePaginate = true;

    /** @var list<string> */
    protected array $with = ['moonshineUser'];

    public function getTitle(): string
    {
        return 'Люди';
    }

    /**
     * Создать человека; пустой пароль заменяется сгенерированным.
     *
     * Плейн-пароль живёт только во flash-сессии и показывается на следующей
     * странице один раз. Дальше в базе остаётся только хеш.
     */
    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        $creating = $item->getKey() === null;
        $plain = trim((string) request()->input('password', ''));
        $generated = null;

        if ($creating && $plain === '') {
            $generated = Str::password(16);

            request()->merge([
                'password' => $generated,
                'password_confirmation' => $generated,
            ]);
        }

        $saved = parent::save($item, $fields);

        if ($generated !== null) {
            session()->flash('user_password', $generated);
            session()->flash('user_password_email', (string) $saved->getOriginal()->email);
        }

        return $saved;
    }

    /**
     * Отключить вход в кабинет, сохранив карточки и токены. Токены после этого
     * не действуют: VerifyBrainToken проверяет is_active владельца.
     */
    #[AsyncMethod]
    public function disableUser(): void
    {
        $user = $this->getItem();

        if (! $user instanceof User) {
            return;
        }

        $user->forceFill(['is_active' => false])->save();

        session()->flash('toast', [
            'type' => 'success',
            'message' => 'Пользователь «'.$user->name.'» отключён. Карточки сохранены.',
        ]);
    }

    protected function pages(): array
    {
        return [
            UserIndexPage::class,
            UserFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'name', 'email'];
    }
}
