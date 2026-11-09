<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Pages\GraphPage;
use App\MoonShine\Pages\ProjectsPage;
use App\MoonShine\Pages\SearchPage;
use App\MoonShine\Pages\SettingsPage;
use App\MoonShine\Resources\KnowledgeEntry\KnowledgeEntryResource;
use App\MoonShine\Resources\KnowledgeSource\KnowledgeSourceResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\SearchLog\SearchLogResource;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  CoreContract<MoonShineConfigurator>  $core
     */
    public function boot(CoreContract $core): void
    {
        $core
            ->resources([
                KnowledgeEntryResource::class,
                KnowledgeSourceResource::class,
                SearchLogResource::class,
                MoonShineUserResource::class,
                MoonShineUserRoleResource::class,
            ])
            ->pages([
                ...$core->getConfig()->getPages(),
                SearchPage::class,
                GraphPage::class,
                ProjectsPage::class,
                SettingsPage::class,
            ]);
    }
}
