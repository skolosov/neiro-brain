<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\KnowledgeSource;
use Illuminate\Support\Carbon;

/**
 * Idempotent message ingestion shared by the proxy, connectors and the API.
 */
final class ConversationIngestor
{
    /**
     * @param  list<array{role: string, content: string, occurred_at?: string|Carbon|null, metadata?: array<string, mixed>|null}>  $messages
     * @param  array{title?: string|null, harness?: string|null, project?: string|null, metadata?: array<string, mixed>|null}  $attributes
     * @return array{conversation: Conversation, added: int}
     */
    public function ingest(
        KnowledgeSource $source,
        string $externalId,
        array $messages,
        array $attributes = [],
    ): array {
        $conversation = Conversation::query()->firstOrNew([
            'source_id' => $source->id,
            'external_id' => $externalId,
        ]);

        $isNew = ! $conversation->exists;

        $conversation->fill([
            'harness' => $attributes['harness'] ?? $conversation->harness,
            'project' => $attributes['project'] ?? $conversation->project,
            'metadata' => array_filter($attributes['metadata'] ?? $conversation->metadata ?? []),
        ]);

        if (($attributes['title'] ?? null) !== null && $attributes['title'] !== '') {
            $conversation->title = $attributes['title'];
        }

        if (! $conversation->exists) {
            $conversation->status = ConversationStatus::Pending->value;
            $conversation->started_at = now();
        }

        $conversation->save();

        $added = $this->append($conversation, $messages);

        if (($conversation->title === null || $conversation->title === '') && $messages !== []) {
            $conversation->title = $this->titleFromMessages($messages);
        }

        if ($isNew && $conversation->last_activity_at === null) {
            $conversation->last_activity_at = now();
        }

        $conversation->save();

        return ['conversation' => $conversation, 'added' => $added];
    }

    /**
     * @param  list<array{role: string, content: string, occurred_at?: string|Carbon|null, metadata?: array<string, mixed>|null}>  $messages
     */
    public function append(Conversation $conversation, array $messages): int
    {
        $added = 0;
        $lastActivity = $conversation->last_activity_at;

        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? 'user');
            $content = trim((string) ($message['content'] ?? ''));

            if ($content === '' || ! in_array($role, ['user', 'assistant', 'system', 'tool'], true)) {
                continue;
            }

            $content = mb_substr($content, 0, 50000);
            $hash = hash('sha256', $role."\n".$content);

            if ($conversation->messages()->where('content_hash', $hash)->exists()) {
                continue;
            }

            $occurredAt = $message['occurred_at'] ?? null;
            $occurredAt = $occurredAt instanceof Carbon
                ? $occurredAt
                : ($occurredAt !== null ? Carbon::parse($occurredAt) : now());

            $conversation->messages()->create([
                'role' => $role,
                'content' => $content,
                'content_hash' => $hash,
                'occurred_at' => $occurredAt,
                'metadata' => $message['metadata'] ?? null,
            ]);

            $lastActivity = $lastActivity === null || $occurredAt->gt($lastActivity) ? $occurredAt : $lastActivity;
            $added++;
        }

        if ($added > 0) {
            $conversation->status = $conversation->status === ConversationStatus::Processing
                ? ConversationStatus::Processing->value
                : ConversationStatus::Pending->value;
            $conversation->last_activity_at = $lastActivity ?? now();
        }

        $conversation->messages_count = $conversation->messages()->count();
        $conversation->tokens_count = (int) $conversation->messages()->sum('token_count');
        $conversation->save();

        return $added;
    }

    /**
     * @param  list<array{role: string, content: string, occurred_at?: string|Carbon|null, metadata?: array<string, mixed>|null}>  $messages
     */
    private function titleFromMessages(array $messages): ?string
    {
        foreach ($messages as $message) {
            if (($message['role'] ?? null) === 'user' && trim((string) ($message['content'] ?? '')) !== '') {
                return Text::limit(trim((string) $message['content']), 120);
            }
        }

        return null;
    }
}
