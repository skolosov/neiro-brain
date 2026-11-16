<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\KnowledgeSearch;
use App\Services\Brain\ProjectContext;
use App\Services\Brain\Text;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Поиск в кабинете.
 *
 * Ищет только по карточкам текущего пользователя: владельца ставит
 * middleware `owner.session`, а глобальный скоуп модели не даст прочитать
 * чужую строку. Карточки здесь только читаются.
 */
class SearchController extends Controller
{
    public function index(Request $request, KnowledgeSearch $search, ProjectContext $context): View
    {
        $query = trim((string) $request->query('q', ''));
        $project = trim((string) $request->query('project', ''));
        $searched = $query !== '';

        $cards = [];

        if ($searched) {
            $result = $search->search(new SearchQuery(
                query: $query,
                project: $project !== '' ? $project : null,
                limit: 40,
            ));

            foreach ($result->hits as $hit) {
                $cards[] = $this->card($hit->entry);
            }
        } else {
            $entries = KnowledgeEntry::query()
                ->approved()
                ->forProject($project !== '' ? $project : null)
                ->orderByDesc('importance')
                ->orderByDesc('updated_at')
                ->limit(30)
                ->get();

            foreach ($entries as $entry) {
                $cards[] = $this->card($entry);
            }
        }

        return view('app.search', [
            'query' => $query,
            'project' => $project,
            'searched' => $searched,
            'cards' => $cards,
            'projects' => $context->projects(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(KnowledgeEntry $entry): array
    {
        $summary = trim((string) ($entry->summary ?? ''));

        if ($summary === '') {
            $summary = Text::limit((string) $entry->content, 300);
        }

        return [
            'id' => (int) $entry->getKey(),
            'title' => (string) $entry->title,
            'summary' => $summary,
            'project' => trim((string) $entry->project) !== '' ? (string) $entry->project : null,
            // Подпись строки: тема, а если её нет — тип знания. Тег для этого
            // не годится: «#verify» в колонке слева читается как мусор.
            'theme' => $entry->theme !== null && trim((string) $entry->theme) !== ''
                ? (string) $entry->theme
                : $entry->kind?->label(),
            'kind' => $entry->kind?->label(),
            'importance' => (int) $entry->importance,
            'updated_at' => $entry->updated_at?->format('d.m.Y'),
        ];
    }
}
