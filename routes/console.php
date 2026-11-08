<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Neiro Brain schedule
|--------------------------------------------------------------------------
|
| The knowledge bank is refreshed every 15 minutes: new conversations are
| summarized into atomic facts, stale entries are reindexed and old search
| logs are pruned. `withoutOverlapping` prevents a slow AI call from stacking
| runs on top of each other.
|
*/

Schedule::command('brain:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20)
    ->onOneServer()
    ->runInBackground();
