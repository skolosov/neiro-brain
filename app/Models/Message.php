<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'token_count' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Message $message): void {
            $message->content_hash ??= hash('sha256', $message->role."\n".$message->content);
            $message->occurred_at ??= now();
            $message->token_count = $message->token_count ?: self::estimateTokens($message->content);
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public static function estimateTokens(string $text): int
    {
        $length = mb_strlen($text);

        return max(1, (int) ceil($length / 3.2));
    }

    public static function fingerprint(string $role, string $content): string
    {
        return hash('sha256', $role."\n".Str::limit($content, 20000, ''));
    }
}
