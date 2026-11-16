<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\PanelRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use MoonShine\Laravel\Models\MoonshineUser;

/**
 * Учётная запись панели для человека.
 *
 * Интерфейс один — панель MoonShine, поэтому у каждого человека должна быть
 * учётная запись панели, привязанная через `moonshine_users.user_id`. Пароль
 * один: вход в панель идёт тем же паролем, что и работа с базой.
 *
 * Созданием занимаются двое: команда `brain:user` и экран «Люди» в панели.
 * Логика у них одна — здесь, иначе два входа разъедутся в правилах ролей.
 */
final class PanelAccount
{
    /**
     * @param  string|null  $password  Плейн-пароль; null или пустая строка — не менять существующий.
     * @param  PanelRole|null  $role  Роль; null — не понижать и не повышать существующую.
     */
    public function sync(User $user, ?string $password = null, ?PanelRole $role = null): MoonshineUser
    {
        $account = MoonshineUser::query()
            ->where('user_id', $user->getKey())
            ->orWhere('email', $user->email)
            ->first() ?? new MoonshineUser;

        $isNew = ! $account->exists;

        $account->name = $user->name;
        $account->email = $user->email;
        $account->user_id = $user->getKey();

        if ($role !== null || $isNew) {
            $account->moonshine_user_role_id = ($role ?? PanelRole::User)->value;
        }

        if ($password !== null && $password !== '') {
            $account->password = Hash::make($password);
        } elseif ($isNew) {
            // Пароля нет — генерируем неотличимый от случайного: войти нельзя,
            // пока администратор не задаст пароль заново.
            $account->password = Hash::make(Str::random(40));
        }

        $account->save();

        return $account;
    }
}
