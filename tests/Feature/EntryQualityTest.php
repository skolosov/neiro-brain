<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\EntryQuality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_verbatim_user_sentence_is_rejected(): void
    {
        $entry = new ExtractedEntry(
            kind: KnowledgeKind::Stack,
            title: 'Это все нужно что бы не приходилось неросети объяснять заново что я использую',
            content: 'это все нужно что бы не приходилось неросети объяснять заново что я использую laravel',
            importance: 3,
            confidence: 0.55,
        );

        $this->assertSame('restatement', app(EntryQuality::class)->reason($entry));
    }

    public function test_card_that_copies_its_sentence_is_rejected(): void
    {
        $entry = new ExtractedEntry(
            kind: KnowledgeKind::Decision,
            title: 'Что бы когда я у нового чата спросил про архитектуру проекта dcell он мне',
            content: 'что бы когда я у нового чата спросил про архитектуру проекта dcell он мне рассказал все по этому проекту',
            importance: 4,
            confidence: 0.55,
        );

        $this->assertSame('restatement', app(EntryQuality::class)->reason($entry));
    }

    public function test_third_person_card_is_kept(): void
    {
        $entry = new ExtractedEntry(
            kind: KnowledgeKind::Stack,
            title: 'Стек пользователя',
            content: 'Пользователь использует Laravel 13 и PostgreSQL во всех новых проектах.',
            importance: 4,
            confidence: 0.9,
        );

        $this->assertNull(app(EntryQuality::class)->reason($entry));
    }

    public function test_short_title_repeating_content_is_kept(): void
    {
        // A theme name may legitimately open the paragraph; only a long
        // truncated head is the signature of a copied sentence.
        $entry = new ExtractedEntry(
            kind: KnowledgeKind::Decision,
            title: 'BM25 для поиска',
            content: 'BM25 для поиска выбран, потому что поиск должен быть объяснимым без эмбеддингов.',
            importance: 4,
            confidence: 0.9,
        );

        $this->assertNull(app(EntryQuality::class)->reason($entry));
    }
}
