<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\Brain\BrainOverview;
use App\Services\Brain\CardSearch;
use Illuminate\Contracts\Support\Renderable;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\FlexibleRender;

/**
 * Overview: what is in the base right now, how it grows, and the health of the
 * import pipeline. Cards, not tables, because the unit of this brain is a card.
 */
#[Icon('chart-bar')]
#[Order(1)]
class Dashboard extends Page
{
    public function getTitle(): string
    {
        return 'Обзор';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return ['#' => $this->getTitle()];
    }

    /**
     * @return iterable<Renderable|string>
     */
    protected function components(): iterable
    {
        $overview = app(BrainOverview::class)->build();
        $cardSearch = app(CardSearch::class);

        $overview['recentCards'] = $overview['recent']
            ->map(fn ($entry): array => $cardSearch->cardFor($entry))
            ->all();

        $overview['seriesMax'] = max(1, max(array_column($overview['series'], 'value')));
        $overview['topicsMax'] = max(1, $overview['topics']->max() ?? 1);
        $overview['searchUrl'] = route('moonshine.page', ['pageUri' => 'search-page']);
        $overview['graphUrl'] = route('moonshine.page', ['pageUri' => 'graph-page']);
        $overview['projectsUrl'] = route('moonshine.page', ['pageUri' => 'projects-page']);

        return [
            FlexibleRender::make(fn (): string => view('moonshine.brain.dashboard', $overview)->render()),
        ];
    }
}
