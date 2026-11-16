<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Text;
use Illuminate\Contracts\View\View;

/**
 * Карточка знаний целиком и история её версий.
 *
 * `findOrFail` работает через глобальный скоуп владельца: id чужой карточки
 * выглядит как несуществующий и даёт 404. История — тоже только своя.
 */
class EntryController extends Controller
{
    public function show(int $entry): View
    {
        $model = KnowledgeEntry::query()->findOrFail($entry);

        $chain = KnowledgeEntry::historyOf((int) $model->getKey());

        $history = $chain
            ->map(function (KnowledgeEntry $version) use ($model): array {
                $summary = trim((string) ($version->summary ?? ''));

                if ($summary === '') {
                    $summary = Text::limit((string) $version->content, 300);
                }

                return [
                    'id' => (int) $version->getKey(),
                    'title' => (string) $version->title,
                    'summary' => $summary,
                    'content' => (string) $version->content,
                    'updated_at' => $version->updated_at?->format('d.m.Y H:i'),
                    'is_current' => (int) $version->getKey() === (int) $model->getKey(),
                ];
            })
            ->values();

        return view('app.entry', [
            'entry' => $model,
            'summary' => $model->summary !== null && trim((string) $model->summary) !== ''
                ? (string) $model->summary
                : Text::limit((string) $model->content, 300),
            'tags' => array_values(array_filter(array_map('trim', $model->tags ?? []))),
            'history' => $history,
        ]);
    }
}
