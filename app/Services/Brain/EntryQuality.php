<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Services\Brain\Dto\ExtractedEntry;

/**
 * Quality gate between extraction and writing.
 *
 * The AI extractor is generous: it happily returns task restatements, code
 * fragments, one-off questions and near-duplicates. This filter keeps only
 * durable knowledge — the kind of fact that is still true in a month.
 */
final class EntryQuality
{
    /** A card that says nothing durable is shorter than this. */
    private const MIN_CONTENT = 30;

    private const MIN_TITLE = 8;

    /** Generic phrases that mean "this is a task, not knowledge". */
    private const NOISE_PATTERNS = [
        '/^пользователь\s+(попросил|попросила|просит|спросил|сказал|написал)\b/u',
        '/^в\s+диалоге\s+/u',
        '/^(здесь|тут)\s+(описано|описывается)\b/u',
        '/^(задача|запрос|вопрос|просьба)\s*:/u',
        '/^(ответ|answer)\s*:/u',
        '/^(например|например,)\s/u',
        '/^(итак|в\s+итоге|короче)\b/u',
        '/^(продолжи|продолжить|давай|давайте)\b/u',
        '/^(спасибо|благодарю|ок|окей|хорошо|понял|поняла|принято)\b/u',
        '/^(сделай|добавь|покажи|напиши|проверь|исправь|убери|посмотри|найди|запусти)\b/u',
        '/\b(хочу|хотел бы|надо|нужно)\s+(что\s?бы|чтобы)\s+ты\b/u',
        '/^(current|this snapshot|note:|todo|file|path|config)\b/u',
        '/^<[a-z]/u',
    ];

    /** Content that is really code or a shell transcript. */
    private const CODE_PATTERNS = [
        '/[{};]\s*$/m',
        '/=>|->|\$\w+\s*=/u',
        '/<\?php|namespace\s+\w+\\\\/u',
        '/^\s*(git|composer|npm|php artisan|docker|curl)\s+\S+/mu',
    ];

    public function __construct(
        private readonly SecretMasker $masker,
    ) {}

    /**
     * @param  list<ExtractedEntry>  $entries
     * @return array{entries: list<ExtractedEntry>, dropped: array<string, int>}
     */
    public function filter(array $entries): array
    {
        $kept = [];
        $dropped = [];
        $seen = [];

        foreach ($entries as $entry) {
            $reason = $this->reason($entry);

            if ($reason === null) {
                $fingerprint = $this->fingerprint($entry);

                if (isset($seen[$fingerprint])) {
                    $reason = 'duplicate';
                } else {
                    $seen[$fingerprint] = true;
                    $kept[] = $entry;
                }
            }

            if ($reason !== null) {
                $dropped[$reason] = ($dropped[$reason] ?? 0) + 1;
            }
        }

        return ['entries' => $kept, 'dropped' => $dropped];
    }

    /**
     * Why an entry is not worth a card, or null when it is.
     */
    public function reason(ExtractedEntry $entry): ?string
    {
        $title = trim($entry->title);
        $content = trim($entry->content);

        if (mb_strlen($title) < self::MIN_TITLE || mb_strlen($content) < self::MIN_CONTENT) {
            return 'too_short';
        }

        // Titles that are cut mid-word, start with a bullet or contain raw
        // markup are transcript fragments, not knowledge.
        if (str_contains($title, '…') || preg_match('/^[-–—*•#>|`]/u', $title) === 1) {
            return 'fragment';
        }

        if (preg_match('/[{}<>]{2,}|=>|<\?php/u', $title) === 1) {
            return 'fragment';
        }

        // A card must be written, not copied. The heuristic extractor turns a
        // user sentence into a card by truncating that same sentence for the
        // title, so a verbatim copy is the signature of transcript noise.
        if ($this->isVerbatimRestatement($title, $content)) {
            return 'restatement';
        }

        if ($entry->importance < 3) {
            return 'low_importance';
        }

        if ($entry->confidence < 0.35) {
            return 'low_confidence';
        }

        if ($this->isRestatedTask($title, $content)) {
            return 'task';
        }

        foreach (self::NOISE_PATTERNS as $pattern) {
            if (preg_match($pattern, mb_strtolower($content)) === 1) {
                return 'noise';
            }
        }

        if ($this->isQuestion($content)) {
            return 'question';
        }

        if ($this->isCode($content)) {
            return 'code';
        }

        return null;
    }

    /**
     * Same normalized text or same title counts as one card.
     */
    private function fingerprint(ExtractedEntry $entry): string
    {
        $text = mb_strtolower(preg_replace('/\s+/u', ' ', $entry->title.' '.$entry->content) ?? '');

        return hash('sha256', preg_replace('/[^\p{L}\p{N} ]+/u', '', $text) ?? $text);
    }

    /**
     * True when the card repeats the source sentence instead of stating a fact.
     *
     * The heuristic fallback uses the sentence itself as content and its
     * truncated head as the title, so a long title that prefixes the content
     * means nothing was summarized. Doing this check here also protects the
     * AI path: a model that echoes the transcript produces the same shape.
     */
    private function isVerbatimRestatement(string $title, string $content): bool
    {
        $title = $this->plain($title);
        $content = $this->plain($content);

        if ($title === '' || $content === '') {
            return false;
        }

        // Written in third person: the sentence was restated, not copied, so a
        // title that opens the paragraph is a theme label and not the truncated
        // head of the user's own line.
        if ($this->opensInThirdPerson($content)) {
            return false;
        }

        if ($title === $content) {
            return true;
        }

        return mb_strlen($title) >= 40 && str_starts_with($content, $title);
    }

    /**
     * A card about the user is written about him, not quoted from him.
     */
    private function opensInThirdPerson(string $content): bool
    {
        return preg_match(
            '/^(пользователь|пользователю|команда|компания|в проекте|в компании|проект|его|их)\b/u',
            $content,
        ) === 1;
    }

    private function plain(string $text): string
    {
        $text = mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return trim(preg_replace('/[^\p{L}\p{N} ]+/u', '', $text) ?? $text);
    }

    private function isQuestion(string $content): bool
    {
        if (! str_ends_with(rtrim($content), '?')) {
            return false;
        }

        // A statement that merely quotes a question is still a statement.
        return mb_substr_count($content, '?') >= 1
            && mb_substr_count($content, '.') === 0;
    }

    private function isCode(string $content): bool
    {
        $matches = 0;

        foreach (self::CODE_PATTERNS as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                $matches++;
            }
        }

        return $matches >= 2;
    }

    private function isRestatedTask(string $title, string $content): bool
    {
        $lower = mb_strtolower($title.' '.$content);

        foreach ([
            'пользователь попросил', 'пользователь просит', 'пользователь предложил',
            'нужно сделать', 'надо сделать', 'требуется реализовать',
        ] as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        return false;
    }
}
