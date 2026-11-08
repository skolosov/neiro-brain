<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use Illuminate\Support\Carbon;

final class Support
{
    /**
     * Extract plain text from the many shapes used by AI harness exports.
     */
    public static function text(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        if (isset($content['text']) && is_string($content['text'])) {
            return trim($content['text']);
        }

        $parts = [];

        foreach ($content as $block) {
            if (is_string($block)) {
                $parts[] = $block;

                continue;
            }

            if (! is_array($block)) {
                continue;
            }

            foreach (['text', 'content', 'input_text', 'output_text'] as $key) {
                if (isset($block[$key]) && is_string($block[$key])) {
                    $parts[] = $block[$key];

                    break;
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function role(array $row): ?string
    {
        $role = $row['role'] ?? $row['message']['role'] ?? $row['payload']['role'] ?? null;

        if (! is_string($role)) {
            $author = $row['message']['author']['role'] ?? $row['author']['role'] ?? null;
            $role = is_string($author) ? $author : null;
        }

        return match ($role) {
            'human' => 'user',
            'ai' => 'assistant',
            'user', 'assistant' => $role,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function occurredAt(array $row): ?Carbon
    {
        $value = $row['timestamp']
            ?? $row['create_time']
            ?? $row['created_at']
            ?? $row['time']
            ?? $row['message']['create_time']
            ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_numeric($value)
                ? Carbon::createFromTimestamp((float) $value)
                : Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function externalId(array $row, string $fallback): string
    {
        foreach (['conversation_id', 'session_id', 'sessionId', 'chat_id', 'id'] as $key) {
            if (isset($row[$key]) && is_scalar($row[$key]) && (string) $row[$key] !== '') {
                return (string) $row[$key];
            }
        }

        return $fallback;
    }
}
