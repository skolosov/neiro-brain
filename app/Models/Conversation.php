<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Services\Brain\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'metadata' => 'array',
            'messages_count' => 'integer',
            'tokens_count' => 'integer',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'summarized_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'source_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(KnowledgeEntry::class);
    }

    /** Conversations that are quiet long enough to be summarized. */
    public function scopeReadyForSummary(Builder $query, int $quietMinutes): Builder
    {
        return $query
            ->where('status', ConversationStatus::Pending->value)
            ->where(function (Builder $query) use ($quietMinutes): void {
                $query->whereNull('last_activity_at')
                    ->orWhere('last_activity_at', '<=', now()->subMinutes($quietMinutes));
            });
    }

    public function transcript(int $maxMessages = 400): string
    {
        $messages = $this->messages()
            ->limit($maxMessages)
            ->get();

        return $messages
            ->map(fn (Message $message): string => sprintf(
                '[%s] %s: %s',
                optional($message->occurred_at)->format('Y-m-d H:i') ?? $message->created_at?->format('Y-m-d H:i'),
                $message->role,
                $message->content,
            ))
            ->implode("\n\n");
    }

    public function getTranscriptTextAttribute(): string
    {
        return Text::limit($this->transcript(), 60000);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }
}
