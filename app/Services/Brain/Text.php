<?php

declare(strict_types=1);

namespace App\Services\Brain;

final class Text
{
    /**
     * Normalize whitespace without changing the visible text.
     */
    public static function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Build an HTML-safe snippet around the first matching needle.
     *
     * @param  list<string>  $needles
     */
    public static function snippet(string $content, array $needles = [], int $length = 320): string
    {
        $plain = self::normalize($content);
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        if ($plain === '') {
            return '';
        }

        $lower = mb_strtolower($plain);
        $position = null;

        foreach ($needles as $needle) {
            $needle = mb_strtolower(trim($needle));

            if ($needle === '') {
                continue;
            }

            $found = mb_strpos($lower, $needle);

            if ($found !== false && ($position === null || $found < $position)) {
                $position = $found;
            }
        }

        if ($position === null) {
            $position = 0;
        }

        $half = (int) floor($length / 3);
        $start = max(0, $position - $half);

        if ($start > 0) {
            $space = mb_strpos($plain, ' ', $start + 1);

            if ($space !== false && $space < $position) {
                $start = $space + 1;
            }
        }

        $snippet = mb_substr($plain, $start, $length);

        if ($start > 0) {
            $snippet = '…'.$snippet;
        }

        if ($start + $length < mb_strlen($plain)) {
            $snippet .= '…';
        }

        return $snippet;
    }

    /**
     * Escape a snippet and wrap query matches in <mark>.
     *
     * @param  list<string>  $needles
     */
    public static function highlight(string $snippet, array $needles): string
    {
        $needles = array_values(array_filter(array_map(
            fn (string $needle): string => trim($needle),
            $needles,
        )));

        if ($needles === []) {
            return e($snippet);
        }

        usort($needles, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $escapedNeedles = array_map(fn (string $needle): string => preg_quote($needle, '/'), $needles);
        $pattern = '/('.implode('|', $escapedNeedles).')/iu';
        $parts = preg_split($pattern, $snippet, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return e($snippet);
        }

        $html = '';

        foreach ($parts as $part) {
            $isMatch = false;

            foreach ($needles as $needle) {
                if (mb_strtolower($part) === mb_strtolower($needle)) {
                    $isMatch = true;

                    break;
                }
            }

            $html .= $isMatch ? '<mark>'.e($part).'</mark>' : e($part);
        }

        return $html;
    }

    public static function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 3.2));
    }

    public static function limit(string $text, int $chars): string
    {
        if (mb_strlen($text) <= $chars) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $chars)).'…';
    }
}
