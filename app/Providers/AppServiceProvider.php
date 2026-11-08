<?php

namespace App\Providers;

use App\Models\KnowledgeEntry;
use App\Observers\KnowledgeEntryObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        KnowledgeEntry::observe(KnowledgeEntryObserver::class);
    }
}
