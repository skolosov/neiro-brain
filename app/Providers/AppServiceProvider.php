<?php

namespace App\Providers;

use App\Models\KnowledgeEntry;
use App\Observers\KnowledgeEntryObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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

        Event::listen(Failed::class, function (Failed $event): void {
            Log::warning('Admin login failed.', [
                'guard' => $event->guard,
                'username' => $event->credentials['email'] ?? $event->credentials['username'] ?? null,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
    }
}
