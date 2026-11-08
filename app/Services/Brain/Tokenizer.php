<?php

declare(strict_types=1);

namespace App\Services\Brain;

use Wamania\Snowball\Stemmer\English;
use Wamania\Snowball\Stemmer\Russian;
use Wamania\Snowball\Stemmer\Stemmer;

/**
 * Language-aware analyzer for Russian and English text.
 *
 * The same analyzer is used for indexing and querying so terms always match.
 */
final class Tokenizer
{
    /** @var array<string, string> */
    private const RU_STOPWORDS = [
        'и', 'в', 'во', 'не', 'что', 'он', 'на', 'я', 'с', 'со', 'как', 'а', 'то', 'все', 'она',
        'так', 'его', 'но', 'да', 'ты', 'к', 'у', 'же', 'вы', 'за', 'бы', 'по', 'только', 'ее',
        'мне', 'было', 'вот', 'от', 'меня', 'еще', 'нет', 'о', 'из', 'ему', 'теперь', 'когда',
        'даже', 'ну', 'вдруг', 'ли', 'если', 'уже', 'или', 'ни', 'быть', 'был', 'него', 'до',
        'вас', 'нибудь', 'опять', 'уж', 'вам', 'ведь', 'там', 'потом', 'себя', 'ничего', 'ей',
        'может', 'они', 'тут', 'где', 'есть', 'надо', 'ней', 'для', 'мы', 'тебя', 'их', 'чем',
        'была', 'сам', 'чтоб', 'без', 'будто', 'чего', 'раз', 'тоже', 'себе', 'под', 'будет',
        'ж', 'тогда', 'кто', 'этот', 'того', 'потому', 'этого', 'какой', 'совсем', 'ним',
        'здесь', 'этом', 'один', 'почти', 'мой', 'тем', 'чтобы', 'нее', 'сейчас', 'были',
        'куда', 'зачем', 'всех', 'никогда', 'можно', 'при', 'наконец', 'два', 'об', 'другой',
        'хоть', 'после', 'над', 'больше', 'тот', 'через', 'эти', 'нас', 'про', 'всего', 'них',
        'какая', 'много', 'разве', 'три', 'эту', 'моя', 'впрочем', 'хорошо', 'свою', 'этой',
        'перед', 'иногда', 'лучше', 'чуть', 'том', 'нельзя', 'такой', 'им', 'более', 'всегда',
        'конечно', 'всю', 'между', 'это', 'какие', 'каких', 'сколько', 'нужно', 'надо', 'пожалуйста',
        'вообще', 'просто', 'давай', 'давайте', 'пусть', 'либо', 'нежели', 'кроме', 'среди',
        'однако', 'зато', 'поэтому', 'поскольку', 'чтобы', 'который', 'которая', 'которые',
        'которого', 'которой', 'которым', 'которых', 'свой', 'своя', 'свои', 'свое', 'своих',
        'пользователь', 'пользователя', 'пользователю', 'пользователем', 'пользователе', 'юзер',
    ];

    /** @var array<string, string> */
    private const EN_STOPWORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'been', 'but', 'by', 'can', 'could', 'did',
        'do', 'does', 'doing', 'for', 'from', 'had', 'has', 'have', 'having', 'he', 'her', 'here',
        'hers', 'him', 'his', 'how', 'i', 'if', 'in', 'into', 'is', 'it', 'its', 'just', 'me',
        'more', 'most', 'my', 'no', 'nor', 'not', 'of', 'on', 'or', 'our', 'ours', 'out', 'over',
        'own', 'same', 'she', 'should', 'so', 'some', 'such', 'than', 'that', 'the', 'their',
        'theirs', 'them', 'then', 'there', 'these', 'they', 'this', 'those', 'through', 'to',
        'too', 'under', 'until', 'up', 'very', 'was', 'we', 'were', 'what', 'when', 'where',
        'which', 'while', 'who', 'whom', 'why', 'will', 'with', 'would', 'you', 'your', 'yours',
        'about', 'also', 'any', 'because', 'been', 'before', 'being', 'between', 'both', 'each',
        'few', 'get', 'got', 'make', 'many', 'much', 'must', 'need', 'now', 'other', 'see',
    ];

    /** @var array<string, true> */
    private array $stopwords = [];

    /** @var array<string, string> */
    private array $stemCache = [];

    private Russian $russian;

    private English $english;

    public function __construct()
    {
        $this->russian = new Russian;
        $this->english = new English;

        foreach ([...self::RU_STOPWORDS, ...self::EN_STOPWORDS] as $word) {
            $this->stopwords[$word] = true;
        }
    }

    /**
     * Normalize raw text for phrase comparisons.
     */
    public function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace('ё', 'е', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Split text into lowercase tokens without stopwords.
     *
     * @return list<string>
     */
    public function rawTokens(string $text): array
    {
        $text = $this->normalize($text);
        $parts = preg_split('/[^\p{L}\p{N}_+#]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];

        foreach ($parts as $part) {
            $part = trim($part, '_#+');

            if ($part === '' || isset($this->stopwords[$part])) {
                continue;
            }

            if (mb_strlen($part) < 2) {
                continue;
            }

            if (preg_match('/^\d+$/u', $part) === 1 && mb_strlen($part) > 12) {
                continue;
            }

            $tokens[] = $part;
        }

        return $tokens;
    }

    /**
     * Analyze text into unique stems.
     *
     * @return list<string>
     */
    public function terms(string $text): array
    {
        return array_keys($this->termFrequencies($text));
    }

    /**
     * Term frequencies keyed by stem.
     *
     * @return array<string, int>
     */
    public function termFrequencies(string $text): array
    {
        $frequencies = [];

        foreach ($this->rawTokens($text) as $token) {
            $term = $this->stem($token);

            if ($term === '') {
                continue;
            }

            $frequencies[$term] = ($frequencies[$term] ?? 0) + 1;
        }

        arsort($frequencies);

        return $frequencies;
    }

    public function stem(string $token): string
    {
        if (isset($this->stemCache[$token])) {
            return $this->stemCache[$token];
        }

        $isCyrillic = preg_match('/\p{Cyrillic}/u', $token) === 1;
        $stemmer = $isCyrillic ? $this->russian : $this->english;

        try {
            $stem = $this->applyStemmer($stemmer, $token);
        } catch (\Throwable) {
            $stem = $token;
        }

        $stem = trim($stem);

        if ($stem === '' || mb_strlen($stem) < 2) {
            $stem = $token;
        }

        return $this->stemCache[$token] = $stem;
    }

    private function applyStemmer(Stemmer $stemmer, string $token): string
    {
        return $stemmer->stem($token);
    }
}
