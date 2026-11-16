<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use MoonShine\Laravel\Models\MoonshineUser;

/**
 * Личность OAuth-согласия: сессия панели, но человек `users`.
 *
 * Passport записывает id пользователя гарда в `oauth_*`, а гард `api` читает
 * `users`. Если отдать Passport учётку панели (`moonshine_users`), OAuth-токен
 * достанется человеку с тем же числовым id — то есть случайному. Поэтому
 * согласие опирается на уже существующую сессию панели (гард `moonshine`), но
 * возвращает привязанного к ней человека: id совпадает с владельцем базы.
 *
 * Отдельного входа у гарда нет: он только читает чужую сессию.
 */
final class PanelPersonGuard implements StatefulGuard
{
    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        $panelUser = Auth::guard('moonshine')->user();

        if (! $panelUser instanceof MoonshineUser || $panelUser->user_id === null) {
            return null;
        }

        $person = User::query()->find($panelUser->user_id);

        // Отключённый человек не должен выдавать доступ: панель такому входу
        // всё равно отвечает 403, а OAuth-согласие — это тот же доступ.
        return $person?->is_active === true ? $person : null;
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->user() !== null;
    }

    public function setUser(Authenticatable $user): static
    {
        return $this;
    }

    public function attempt(array $credentials = [], $remember = false): bool
    {
        return false;
    }

    public function once(array $credentials = []): bool
    {
        return false;
    }

    public function login(Authenticatable $user, $remember = false): void
    {
        // Вход — только через панель: здесь логинить некуда и незачем.
    }

    public function loginUsingId($id, $remember = false): Authenticatable|false
    {
        return false;
    }

    public function onceUsingId($id): Authenticatable|false
    {
        return false;
    }

    public function viaRemember(): bool
    {
        return false;
    }

    public function logout(): void
    {
        Auth::guard('moonshine')->logout();
    }
}
