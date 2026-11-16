<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasApiTokens as HasPersonalTokens;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use MoonShine\Laravel\Models\MoonshineUser;

#[Fillable(['name', 'email', 'password', 'locale', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /**
     * Два вида токенов: личные `nb-…` из панели (свой трейт) и OAuth-токены
     * Passport, которыми клиент ходит после согласия человека. Имена методов у
     * трейтов разные (`apiTokens()` и `tokens()`), поэтому они не конфликтуют.
     *
     * @use HasFactory<UserFactory>
     */
    use HasApiTokens, HasFactory, HasPersonalTokens, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Связь с админкой MoonShine: `moonshine_users.user_id`.
     *
     * У человека может не быть доступа в админку, поэтому связь необязательная.
     */
    public function moonshineUser(): HasOne
    {
        return $this->hasOne(MoonshineUser::class, 'user_id');
    }

    /**
     * Email админского аккаунта для списка людей. Пустая строка — доступа нет.
     */
    public function getAdminEmailAttribute(): string
    {
        return (string) ($this->moonshineUser?->email ?? '');
    }
}
