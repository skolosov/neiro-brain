<?php

namespace Tests;

use App\Settings\SettingsInitializer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (Schema::hasTable('settings')) {
                app(SettingsInitializer::class)->ensure();
            }
        } catch (\Throwable) {
            // Unit tests that do not use the database skip settings bootstrapping.
        }
    }
}
