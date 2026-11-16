<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\ApiToken;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Личные токены пользователя.
 *
 * Отдельный трейт, а не Sanctum: таблица и проверка способностей свои, но
 * точка входа у модели та же — `$user->apiTokens()`.
 */
trait HasApiTokens
{
    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }
}
