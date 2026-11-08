<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntryStatus;
use App\Enums\KnowledgeKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class KnowledgeEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (KnowledgeEntry $entry): void {
            if (empty($entry->content_hash)) {
                $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', (string) $entry->content) ?? (string) $entry->content);
                $hash = hash('sha256', $normalized);

                while (self::query()->where('content_hash', $hash)->exists()) {
                    $hash = hash('sha256', $normalized.'|'.Str::random(8));
                }

                $entry->content_hash = $hash;
            }

            $entry->language ??= 'ru';
            $entry->confidence ??= 1;
            $entry->importance ??= 3;
        });
    }

    protected function casts(): array
    {
        return [
            'kind' => KnowledgeKind::class,
            'status' => EntryStatus::class,
            'tags' => 'array',
            'metadata' => 'array',
            'importance' => 'integer',
            'confidence' => 'float',
            'tokens_count' => 'integer',
            'terms_count' => 'integer',
            'occurred_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'indexed_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'source_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'entry_id');
    }

    public function terms(): HasManyThrough
    {
        return $this->hasManyThrough(
            KnowledgeTerm::class,
            KnowledgeChunk::class,
            'entry_id',
            'chunk_id',
        );
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', EntryStatus::Approved->value);
    }

    public function scopeSearchable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            EntryStatus::Approved->value,
            EntryStatus::Draft->value,
        ]);
    }

    public function scopeForProject(Builder $query, ?string $project): Builder
    {
        return $project === null || $project === ''
            ? $query
            : $query->where('project', $project);
    }

    /** @param list<string> $kinds */
    public function scopeOfKinds(Builder $query, array $kinds): Builder
    {
        return $kinds === [] ? $query : $query->whereIn('kind', $kinds);
    }

    /** Comma-separated tags input used by the admin panel and API. */
    public function getTagsTextAttribute(): string
    {
        return implode(', ', $this->tags ?? []);
    }

    public function setTagsTextAttribute(?string $value): void
    {
        $this->tags = collect(explode(',', (string) $value))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function getKindLabelAttribute(): string
    {
        return $this->kind->label();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getShortContentAttribute(): string
    {
        return Str::limit($this->content, 160);
    }
}
