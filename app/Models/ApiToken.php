<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Личный токен пользователя для MCP и HTTP API.
 *
 * Хранится только хеш: сам токен показывается один раз при выпуске. Отзыв —
 * это удаление строки, поэтому отдельного revoked_at нет.
 */
class ApiToken extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Способности одной строкой — для колонки админки.
     */
    public function getAbilitiesTextAttribute(): string
    {
        return implode(', ', $this->abilities ?? []);
    }

    /**
     * Право read подразумевается правом write: кто может писать, тот может и
     * читать. Обратное неверно — токен только для чтения писать не умеет.
     */
    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities ?? [];

        if (in_array($ability, $abilities, true)) {
            return true;
        }

        return $ability === 'read' && in_array('write', $abilities, true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Отметить использование, но не чаще раза в минуту: MCP-клиент делает
     * несколько запросов подряд, и писать в базу на каждый — лишняя нагрузка.
     */
    public function touchLastUsed(): void
    {
        if ($this->last_used_at !== null && $this->last_used_at->gt(now()->subMinute())) {
            return;
        }

        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }
}
