<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

/**
 * Подключённые OAuth-приложения: кто получил доступ и когда.
 *
 * OAuth-токены живут в таблицах Passport, а не в `api_tokens`, поэтому в
 * панели у них свой блок. Отзыв — удаление строки токена: Passport считает
 * отсутствующий токен отозванным, и следующий запрос клиента получает 401.
 * Refresh-токен удаляем вместе с access-токеном, иначе клиент обновит доступ.
 */
final class OAuthConnections
{
    /**
     * @return Collection<int, Token>
     */
    public function visible(?int $ownerId, bool $all): Collection
    {
        $query = Passport::token()->newQuery()
            ->with('client')
            ->where('revoked', false)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if (! $all) {
            if ($ownerId === null) {
                // Вход без человека не должен видеть чужие приложения даже
                // теоретически: пустой контекст — это «всё», поэтому режем явно.
                $query->whereRaw('1 = 0');
            } else {
                $query->where('user_id', $ownerId);
            }
        }

        return $query->latest()->get();
    }

    public function revoke(Token $token): void
    {
        DB::transaction(function () use ($token): void {
            $token->refreshToken()->delete();
            $token->delete();
        });
    }
}
