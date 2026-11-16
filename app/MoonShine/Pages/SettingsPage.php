<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Settings\RetrievalSettings;
use App\Settings\RulesSettings;
use App\Settings\SecuritySettings;
use App\Settings\SettingsInitializer;
use App\Support\PanelAccess;
use Illuminate\Contracts\Support\Renderable;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\FlexibleRender;

#[Icon('cog-6-tooth')]
#[Group('База знаний', 'book-open')]
#[Order(90)]
class SettingsPage extends Page
{
    public function getTitle(): string
    {
        return 'Настройки';
    }

    /**
     * Настройки базы — администраторские: обычному человеку страница закрыта,
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
            'retrieval' => app(RetrievalSettings::class),
            'security' => app(SecuritySettings::class),
            'rules' => app(RulesSettings::class),
        ];

        return [
            FlexibleRender::make(fn (): string => view('moonshine.brain.settings', $data)->render()),
        ];
    }
}
