<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Enums\EntryStatus;
use App\Models\Conversation;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Settings\SyncSettings;

final class KnowledgeWriter
{
    public function __construct(
        private readonly SecretMasker $masker,
        private readonly SyncSettings $syncSettings,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{entry: KnowledgeEntry, created: bool}|null
     */
    public function write(
        ExtractedEntry $data,
        ?Conversation $conversation = null,
        ?KnowledgeSource $source = null,
        ?EntryStatus $status = null,
        array $metadata = [],
    ): ?array {
        $title = $this->masker->mask($data->title)->text;
        $content = $this->masker->mask($data->content)->text;
        $details = $data->details !== null ? $this->masker->mask($data->details)->text : null;

        $title = $this->cleanTitle($title);
        $content = trim($content);
        $details = $details !== null ? trim($details) : null;

        if ($content === '') {
            return null;
        }

        if ($title === '') {
            $title = Text::limit($content, 90);
        }

        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', $content) ?? $content);
        $hash = hash('sha256', $normalized);
        $existing = KnowledgeEntry::query()->where('content_hash', $hash)->first();

        if ($existing !== null) {
            $existing->tags = array_values(array_unique([...($existing->tags ?? []), ...$data->tags]));
            $existing->importance = max($existing->importance, $data->importance);
            $existing->confidence = max((float) $existing->confidence, $data->confidence);
            $existing->last_verified_at = now();
            $existing->project = $existing->project ?: $data->project;
            $existing->save();

            return ['entry' => $existing, 'created' => false];
        }

        $entry = KnowledgeEntry::query()->create([
            'source_id' => $source?->id ?? $conversation?->source_id,
            'conversation_id' => $conversation?->id,
            'kind' => $data->kind->value,
            'status' => ($status ?? ($this->syncSettings->auto_approve ? EntryStatus::Approved : EntryStatus::Draft))->value,
            'title' => $title,
            'content' => $content,
            'details' => $details,
            'project' => $data->project,
            'tags' => $data->tags,
            'importance' => $data->importance,
            'confidence' => $data->confidence,
            'language' => $this->language($content),
            'content_hash' => $hash,
            'occurred_at' => $conversation?->last_activity_at ?? now(),
            'last_verified_at' => now(),
            'metadata' => array_filter([
                'engine' => $metadata['engine'] ?? null,
                'conversation_external_id' => $conversation?->external_id,
            ]),
        ]);

        return ['entry' => $entry, 'created' => true];
    }

    /**
     * @param  list<ExtractedEntry>  $data
     * @param  array<string, mixed>  $metadata
     * @return array{created: int, updated: int}
     */
    public function writeMany(
        array $data,
        ?Conversation $conversation = null,
        ?KnowledgeSource $source = null,
        array $metadata = [],
    ): array {
        $created = 0;
        $updated = 0;

        foreach ($data as $item) {
            $result = $this->write($item, $conversation, $source, null, $metadata);

            if ($result === null) {
                continue;
            }

            $result['created'] ? $created++ : $updated++;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    private function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);

        return Text::limit($title, 255);
    }

    private function language(string $text): string
    {
        $length = max(1, mb_strlen($text));
        $cyrillic = preg_match_all('/\p{Cyrillic}/u', $text) ?: 0;

        return ($cyrillic / $length) > 0.15 ? 'ru' : 'en';
    }
}
