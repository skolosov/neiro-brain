<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Settings\RetrievalSettings;

final class Chunker
{
    public function __construct(
        private readonly RetrievalSettings $settings,
    ) {}

    /**
     * Split text into overlapping chunks close to the configured size.
     *
     * @return list<string>
     */
    public function chunk(string $text, ?int $maxChars = null, ?int $overlapChars = null): array
    {
        $maxChars = max(200, $maxChars ?? $this->settings->chunk_chars);
        $overlapChars = min($maxChars - 1, max(0, $overlapChars ?? $this->settings->chunk_overlap));
        $text = Text::normalize($text);

        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) <= $maxChars) {
            return [$text];
        }

        $units = $this->units($text, $maxChars);
        $chunks = [];
        $buffer = '';

        foreach ($units as $unit) {
            if ($buffer === '') {
                $buffer = $unit;

                continue;
            }

            if (mb_strlen($buffer) + 1 + mb_strlen($unit) <= $maxChars) {
                $buffer .= "\n".$unit;

                continue;
            }

            $chunks[] = $buffer;
            $buffer = trim($this->tail($buffer, $overlapChars)."\n".$unit);

            while (mb_strlen($buffer) > $maxChars) {
                $slice = mb_substr($buffer, 0, $maxChars);
                $rest = trim(mb_substr($buffer, $maxChars));
                $chunks[] = $slice;
                $buffer = $overlapChars > 0
                    ? trim($this->tail($slice, $overlapChars).' '.$rest)
                    : $rest;

                if ($buffer === '' || mb_strlen($buffer) <= $overlapChars) {
                    $buffer = $rest;

                    break;
                }
            }
        }

        if (trim($buffer) !== '') {
            $chunks[] = trim($buffer);
        }

        return array_values(array_filter($chunks, fn (string $chunk): bool => $chunk !== ''));
    }

    /**
     * @return list<string>
     */
    private function units(string $text, int $maxChars): array
    {
        $paragraphs = preg_split('/\n{2,}/u', $text) ?: [$text];
        $units = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) <= $maxChars) {
                $units[] = $paragraph;

                continue;
            }

            $sentences = preg_split('/(?<=[.!?…])\s+/u', $paragraph) ?: [$paragraph];

            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);

                if ($sentence === '') {
                    continue;
                }

                if (mb_strlen($sentence) <= $maxChars) {
                    $units[] = $sentence;

                    continue;
                }

                foreach (preg_split('/\s+/u', $sentence) ?: [] as $word) {
                    if ($word !== '') {
                        $units[] = $word;
                    }
                }
            }
        }

        return $units;
    }

    private function tail(string $text, int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        return trim(mb_substr($text, -$length));
    }
}
