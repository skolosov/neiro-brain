<?php

namespace Tests;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use App\Settings\SettingsInitializer;
use App\Support\OwnerContext;
use DateTimeInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /** Владелец, под которым работают feature-тесты по умолчанию. */
    protected const OWNER_EMAIL = 'owner@neiro-brain.test';

    protected function setUp(): void
    {
        parent::setUp();

        // Passport подписывает OAuth-токены файловыми ключами, а они не в git.
        // На чистом клоне тесты выпускают их сами, иначе падают с «Key file …
        // does not exist» ещё до первого assert.
        if (! is_file(storage_path('oauth-private.key'))) {
            Artisan::call('passport:keys');
        }

        try {
            if (Schema::hasTable('settings')) {
                app(SettingsInitializer::class)->ensure();
            }
        } catch (\Throwable) {
            // Unit tests that do not use the database skip settings bootstrapping.
        }

        try {
            if (Schema::hasTable('users')) {
                // Владелец по умолчанию для сервисных тестов. HTTP-тесты
                // дополнительно вызывают withOwner(): он выпускает токен и
                // подставляет заголовок, как это сделал бы настоящий клиент.
                app(OwnerContext::class)->set($this->owner());
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

    /**
     * Поставить владельца в контекст и подставить его личный токен в заголовок.
     *
     * Так HTTP-тесты ходят тем же путём, что боевой клиент: без токена
     * middleware не пустит, а владелец берётся из строки api_tokens.
     */
    protected function withOwner(User $owner): User
    {
        app(OwnerContext::class)->set($owner);

        $this->withHeader('Authorization', 'Bearer '.$this->issueToken($owner)['plain']);

        return $owner;
    }

    /**
     * Выпустить токен для теста.
     *
     * @param  list<string>  $abilities
     * @return array{token: ApiToken, plain: string}
     */
    protected function issueToken(
        User $owner,
        array $abilities = ['read', 'write'],
        ?DateTimeInterface $expiresAt = null,
    ): array {
        return app(TokenIssuer::class)->issue($owner, 'Тестовый токен', $abilities, $expiresAt);
    }

    /** Заголовки для MCP-запроса с личным токеном. */
    protected function mcpHeaders(string $plain): array
    {
        return [
            'Authorization' => 'Bearer '.$plain,
            'Accept' => 'application/json, text/event-stream',
        ];
    }
}
