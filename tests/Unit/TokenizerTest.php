<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Brain\Tokenizer;
use PHPUnit\Framework\TestCase;

class TokenizerTest extends TestCase
{
    private Tokenizer $tokenizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenizer = new Tokenizer;
    }

    public function test_russian_forms_share_a_stem(): void
    {
        $this->assertSame($this->tokenizer->stem('борща'), $this->tokenizer->stem('борщ'));
        $this->assertSame($this->tokenizer->stem('приготовил'), $this->tokenizer->stem('приготовить'));
        $this->assertSame($this->tokenizer->stem('проекта'), $this->tokenizer->stem('проект'));
    }

    public function test_analyzer_drops_question_words_and_stopwords(): void
    {
        $terms = $this->tokenizer->terms('Как приготовить борщ?');

        $this->assertContains('приготов', $terms);
        $this->assertContains('борщ', $terms);
        $this->assertNotContains('как', $terms);
        $this->assertNotContains('у', $terms);
    }

    public function test_yo_is_normalized(): void
    {
        $this->assertSame($this->tokenizer->terms('ещё ёлка'), $this->tokenizer->terms('еще елка'));
    }

    public function test_english_and_technical_tokens_are_kept(): void
    {
        $terms = $this->tokenizer->terms('Laravel 13 + PostgreSQL, BM25 and MCP');

        $this->assertContains('laravel', $terms);
        $this->assertContains('postgresql', $terms);
        $this->assertContains('bm25', $terms);
        $this->assertContains('mcp', $terms);
    }

    public function test_term_frequencies_count_repeats(): void
    {
        $frequencies = $this->tokenizer->termFrequencies('борщ борщ борща суп');

        $this->assertSame(3, $frequencies['борщ'] ?? 0);
    }
}
