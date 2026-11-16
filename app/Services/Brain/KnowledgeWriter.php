<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Enums\EntryStatus;
use App\Enums\KnowledgeTheme;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Support\OwnerContext;
use LogicException;

final class KnowledgeWriter
{
    public function __construct(
        private readonly SecretMasker $masker,
        private readonly Indexer $indexer,
        private readonly OwnerContext $owners,
    ) {}

    /**
     * @param  EntryStatus|null  $status  Оставлен для совместимости вызовов: запись всегда approved.
     * @param  array<string, mixed>  $metadata
     * @return array{entry: KnowledgeEntry, created: bool}|null
     */
    public function write(
        ExtractedEntry $data,
        ?EntryStatus $status = null,
        array $metadata = [],
    ): ?array {
        // Владелец — только из контекста запроса. Пустой контекст — ошибка:
        // иначе карточка досталась бы случайному пользователю.
        $ownerId = $this->owners->id();

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
        $existing = KnowledgeEntry::query()
            ->ownedBy($ownerId)
            ->where('content_hash', $hash)
            ->first();

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
            'owner_id' => $ownerId,
            'kind' => $data->kind->value,
            // Запись всегда опубликована: черновиков в потоке больше нет.
            'status' => EntryStatus::Approved->value,
            'title' => $title,
            'content' => $content,
            'summary' => $data->summary !== null ? $this->masker->mask($data->summary)->text : Text::limit($content, 300),
            'details' => $details,
            'project' => $data->project,
            // Новые карточки получают каноническую тему. Старые и TopicMap не
            // переписываются: у них свои написания, и это осознанная история.
            'theme' => KnowledgeTheme::normalize($data->theme),
            'scope' => $data->scope->value,
            'tags' => $data->tags,
            'importance' => $data->importance,
            'confidence' => $data->confidence,
            'language' => $this->language($content),
            'content_hash' => $hash,
            'occurred_at' => now(),
            'last_verified_at' => now(),
            'metadata' => array_filter([
                'engine' => $metadata['engine'] ?? null,
            ]),
        ]);

        return ['entry' => $entry, 'created' => true];
    }

    /**
     * Replace a card with its new version.
     *
     * The old row is kept as history and marked superseded: search stops
     * returning it, and nothing the user said is destroyed.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function supersede(
        KnowledgeEntry $old,
        ExtractedEntry $data,
        array $metadata = [],
    ): KnowledgeEntry {
        $new = $this->write($data, null, $metadata);

        if ($new === null) {
            return $old;
        }

        // Новая версия принадлежит тому же владельцу, что и старая: подменить
        // чужую карточку нельзя даже зная её id.
        if ((int) $old->owner_id !== (int) $new['entry']->owner_id) {
            throw new LogicException('Нельзя заменить карточку другого владельца.');
        }

        $old->forceFill(['superseded_by' => $new['entry']->getKey()])->save();
        $this->indexer->index($old);

        return $new['entry'];
    }

    /**
     * Пометить перечисленные карточки заменёнными только что записанной.
     *
     * Так работает слияние дублей: новая карточка остаётся в поиске, старые
     * уходят в историю. Трогаем только карточки текущего владельца: чужой id
     * не даёт права подменить карточку. Возвращаем и те id, которые не удалось
     * принять, чтобы инструмент честно сказал о них в ответе.
     *
     * @param  list<int>  $ids
     * @return array{linked: list<int>, ignored: list<int>}
     */
    public function supersedeWith(KnowledgeEntry $new, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id): bool => $id > 0 && $id !== (int) $new->getKey(),
        )));

        if ($ids === []) {
            return ['linked' => [], 'ignored' => []];
        }

        $ownerId = $this->owners->id();

        // Скоуп владельца снят: чужие карточки нужно увидеть, чтобы честно
        // сообщить о них, а не выдать их за несуществующие. Скоуп актуальности
        // остаётся: уже заменённую карточку второй раз не перезаменяем.
        $cards = KnowledgeEntry::query()
            ->withoutGlobalScope('owner')
            ->whereIn('id', $ids)
            ->get();

        $linked = [];
        $ignored = [];

        foreach ($cards as $old) {
            if ((int) $old->owner_id !== $ownerId) {
                $ignored[] = (int) $old->getKey();

                continue;
            }

            $old->forceFill(['superseded_by' => $new->getKey()])->save();
            $this->indexer->index($old);
            $linked[] = (int) $old->getKey();
        }

        $found = $cards->map(fn (KnowledgeEntry $entry): int => (int) $entry->getKey())->all();
        $ignored = array_values(array_unique([...$ignored, ...array_diff($ids, $found)]));

        return ['linked' => $linked, 'ignored' => $ignored];
    }

    /**
     * @param  list<ExtractedEntry>  $data
     * @param  array<string, mixed>  $metadata
     * @return array{created: int, updated: int}
     */
    public function writeMany(
        array $data,
        array $metadata = [],
    ): array {
        $created = 0;
        $updated = 0;

        foreach ($data as $item) {
            $result = $this->write($item, null, $metadata);

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
