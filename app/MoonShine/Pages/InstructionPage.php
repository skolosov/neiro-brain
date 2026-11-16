<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\Brain\SkillText;
use App\Settings\SettingsInitializer;
use App\Support\PanelAccess;
use Illuminate\Contracts\Support\Renderable;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\FlexibleRender;

#[Icon('document-text')]
#[Group('База знаний', 'book-open')]
#[Order(91)]
class InstructionPage extends Page
{
    public function getTitle(): string
    {
        return 'Инструкция';
    }

    /**
     * Протокол агента — администраторский: обычному человеку страница закрыта,
     * даже если он введёт адрес руками.
     */
    protected function prepareBeforeRender(): void
    {
        if (! PanelAccess::isAdmin()) {
            $this->throw403();
        }

        parent::prepareBeforeRender();
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
        app(SettingsInitializer::class)->ensure();

        $data = [
            'text' => app(SkillText::class)->current(),
        ];

        return [
            FlexibleRender::make(fn (): string => view('moonshine.brain.instruction', $data)->render()),
        ];
    }
}
