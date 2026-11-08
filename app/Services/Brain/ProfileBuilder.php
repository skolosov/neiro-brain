<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Models\KnowledgeEntry;
use Illuminate\Support\Collection;

/**
 * Compiles a compact "who is the user" profile for MCP prompts.
 */
final class ProfileBuilder
{
    public function build(int $maxChars = 6000): string
    {
        /** @var Collection<int, KnowledgeEntry> $entries */
        $entries = KnowledgeEntry::query()
            ->approved()
            ->whereIn('kind', ['profile', 'preference', 'company', 'stack', 'style', 'decision'])
            ->orderByDesc('importance')
            ->orderByDesc('last_verified_at')
            ->limit(40)
            ->get();

        if ($entries->isEmpty()) {
            return 'База знаний пока пуста.';
        }

        $lines = ['# Профиль пользователя (Neiro Brain)', ''];

        foreach ($entries as $entry) {
            $line = sprintf(
                '- **%s**: %s%s',
                $entry->title,
                trim($entry->content),
                $entry->project ? ' _(проект: '.$entry->project.')_' : '',
            );

            if (mb_strlen(implode("\n", $lines).$line) > $maxChars) {
                break;
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
