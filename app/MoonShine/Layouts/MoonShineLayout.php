<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Pages\GraphPage;
use App\MoonShine\Pages\ProjectsPage;
use App\MoonShine\Pages\SearchPage;
use App\MoonShine\Pages\SettingsPage;
use App\MoonShine\Resources\Conversation\ConversationResource;
use App\MoonShine\Resources\KnowledgeEntry\KnowledgeEntryResource;
use App\MoonShine\Resources\KnowledgeSource\KnowledgeSourceResource;
use App\MoonShine\Resources\SearchLog\SearchLogResource;
use MoonShine\ColorManager\ColorManager;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;

final class MoonShineLayout extends AppLayout
{
    /**
     * @var null|class-string<PaletteContract>
     */
    protected ?string $palette = PurplePalette::class;

    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        return [
            ...parent::menu(),
            MenuGroup::make('База знаний', [
                MenuItem::make(SearchPage::class, 'Поиск', 'magnifying-glass'),
                MenuItem::make(GraphPage::class, 'Граф', 'share'),
                MenuItem::make(ProjectsPage::class, 'Проекты', 'folder'),
                MenuItem::make(KnowledgeEntryResource::class, 'Знания', 'book-open'),
                MenuItem::make(ConversationResource::class, 'Диалоги', 'chat-bubble-left-right'),
                MenuItem::make(KnowledgeSourceResource::class, 'Источники', 'circle-stack'),
                MenuItem::make(SearchLogResource::class, 'Поисковые запросы', 'magnifying-glass'),
                MenuItem::make(SettingsPage::class, 'Настройки', 'cog-6-tooth'),
            ], 'book-open'),
        ];
    }

    /**
     * @param  ColorManager  $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);
    }
}
