<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Pages\ConnectPage;
use App\MoonShine\Pages\GraphPage;
use App\MoonShine\Pages\InstructionPage;
use App\MoonShine\Pages\ProjectsPage;
use App\MoonShine\Pages\SearchPage;
use App\MoonShine\Pages\SettingsPage;
use App\MoonShine\Resources\ApiToken\ApiTokenResource;
use App\MoonShine\Resources\KnowledgeEntry\KnowledgeEntryResource;
use App\MoonShine\Resources\User\UserResource;
use App\Support\PanelAccess;
use MoonShine\ColorManager\ColorManager;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Contracts\MenuManager\MenuElementContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;

/**
 * Меню панели. Интерфейс один, поэтому разграничение живёт здесь: обычный
 * человек видит знания, проекты, граф, свои токены и профиль, а люди,
 * администраторы, роли и настройки остаются администратору.
 */
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
        $isAdmin = static fn (): bool => PanelAccess::isAdmin();

        // Системная группа MoonShine (администраторы и роли) появляется только у админа.
        $system = array_map(
            static fn (MenuElementContract $item): MenuElementContract => $item->canSee($isAdmin),
            parent::menu(),
        );

        return [
            ...$system,
            MenuGroup::make('База знаний', [
                MenuItem::make(SearchPage::class, 'Поиск', 'magnifying-glass'),
                MenuItem::make(GraphPage::class, 'Граф', 'share'),
                MenuItem::make(ProjectsPage::class, 'Проекты', 'folder'),
                MenuItem::make(KnowledgeEntryResource::class, 'Знания', 'book-open'),
                MenuItem::make(SettingsPage::class, 'Настройки', 'cog-6-tooth')->canSee($isAdmin),
                MenuItem::make(InstructionPage::class, 'Инструкция', 'document-text')->canSee($isAdmin),
            ], 'book-open'),
            MenuGroup::make('Люди', [
                MenuItem::make(UserResource::class, 'Люди', 'users')->canSee($isAdmin),
            ], 'users'),
            MenuGroup::make('Доступ', [
                MenuItem::make(ApiTokenResource::class, 'Токены', 'key'),
                MenuItem::make(ConnectPage::class, 'Подключение', 'link'),
            ], 'key'),
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
