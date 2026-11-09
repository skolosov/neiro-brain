<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\Brain\BrainOverview;
use Illuminate\Contracts\Support\Renderable;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\FlexibleRender;

/**
 * Projects as cards: how many cards each project has, which topics dominate it
 * and when it was last touched.
 */
#[Icon('folder')]
#[Group('База знаний', 'book-open')]
#[Order(10)]
class ProjectsPage extends Page
{
    public function getTitle(): string
    {
        return 'Проекты';
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
        $overview = app(BrainOverview::class);

        $data = [
            'projects' => $overview->projectCards(48),
            'searchUrl' => route('moonshine.page', ['pageUri' => 'search-page']),
            'graphUrl' => route('moonshine.page', ['pageUri' => 'graph-page']),
        ];

        return [
            FlexibleRender::make(fn (): string => view('moonshine.brain.projects', $data)->render()),
        ];
    }
}
