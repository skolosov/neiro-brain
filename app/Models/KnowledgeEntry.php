<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntryStatus;
use App\Enums\KnowledgeKind;
use App\Enums\KnowledgeScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class KnowledgeEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Set while a history view runs: superseded rows stay hidden everywhere else
     * so a stale card can never reach search or the model by accident.
     */
    protected static bool $withHistory = false;

    protected static function booted(): void
    {
        static::addGlobalScope('current', function (Builder $builder): void {
            if (! static::$withHistory) {
                $builder->whereNull($builder->getModel()->qualifyColumn('superseded_by'));
            }
        });

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
            'scope' => KnowledgeScope::class,
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

    /**
     * Run a callback with superseded cards visible (history screens only).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withHistory(callable $callback): mixed
    {
        static::$withHistory = true;

        try {
            return $callback();
        } finally {
            static::$withHistory = false;
        }
    }

    /**
     * Version chain of one card, oldest first.
     *
     * `superseded_by` points forward (old row → its replacement), so earlier
     * versions are found by looking for rows that point at the current one.
     *
     * @return Collection<int, self>
     */
    public static function historyOf(int $entryId): Collection
    {
        return static::withHistory(function () use ($entryId) {
            $entry = static::withoutHistoryScope()->find($entryId);

            if ($entry === null) {
                return collect();
            }

            $chain = collect([$entry]);
            $cursor = $entry;
            $guard = 0;

            while ($cursor instanceof self && $guard < 100) {
                $previous = static::withoutHistoryScope()
                    ->where('superseded_by', $cursor->getKey())
                    ->orderByDesc('id')
                    ->first();

                if ($previous === null) {
                    break;
                }

                $chain->prepend($previous);
                $cursor = $previous;
                $guard++;
            }

            return $chain->values();
        });
    }

    private static function withoutHistoryScope(): Builder
    {
        return static::query()->withoutGlobalScope('current');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', EntryStatus::Approved->value);
    }

    /**
     * Current cards only. A superseded row is history: the newer version says
     * the same thing better, and handing both to the model produces contradictions.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_by');
    }

    /**
     * Cards that apply everywhere: profile and environment. Project cards are
     * deliberately excluded — they belong to one repository only.
     */
    public function scopeGlobalScope(Builder $query): Builder
    {
        return $query->whereIn('scope', [
            KnowledgeScope::Profile->value,
            KnowledgeScope::Environment->value,
        ]);
    }

    /**
     * Cards of one project plus the global ones that shape every answer.
     */
    public function scopeForContext(Builder $query, ?string $project): Builder
    {
        if ($project === null || $project === '') {
            return $query->globalScope();
        }

        return $query->where(function (Builder $builder) use ($project): void {
            $builder->where('project', $project)
                ->orWhereIn('scope', [
                    KnowledgeScope::Profile->value,
                    KnowledgeScope::Environment->value,
                ]);
        });
    }

    /** The replacement of a superseded card, if any. */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by');
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
