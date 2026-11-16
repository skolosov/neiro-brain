<?php

namespace Tests;

use App\Models\User;
use App\Settings\SettingsInitializer;
use App\Support\OwnerContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /** Владелец, под которым работают feature-тесты по умолчанию. */
    protected const OWNER_EMAIL = 'owner@neiro-brain.test';

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

        try {
            if (Schema::hasTable('users')) {
                // Знания всегда принадлежат владельцу: тест получает его так же,
                // как боевой запрос — через OwnerContext.
                $this->withOwner($this->owner());
            }
        } catch (\Throwable) {
            // Tests without migrations (the landing page) have no users table.
        }
    }

    protected function tearDown(): void
    {
        if ($this->app !== null) {
            app(OwnerContext::class)->forget();
        }

        parent::tearDown();
    }

    /** Владелец по умолчанию; живёт в рамках одного теста (RefreshDatabase). */
    protected function owner(): User
    {
        return User::query()->firstOrCreate(
            ['email' => self::OWNER_EMAIL],
            [
                'name' => 'Владелец знаний',
                'password' => Hash::make('password'),
            ],
        );
    }

    /** Поставить другого владельца в контекст запроса. */
    protected function withOwner(User $owner): User
    {
        app(OwnerContext::class)->set($owner);

        return $owner;
    }
}
