<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Settings\AiSettings;
use App\Settings\ProxySettings;
use App\Settings\SecuritySettings;
use App\Settings\SettingsInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_initializer_writes_defaults_for_every_group(): void
    {
        app(SettingsInitializer::class)->ensure();

        foreach (['ai', 'retrieval', 'proxy', 'sync', 'security'] as $group) {
            $this->assertGreaterThan(0, DB::table('settings')->where('group', $group)->count(), $group);
        }

        $this->assertSame('mock', app(ProxySettings::class)->upstream);
        $this->assertTrue(app(AiSettings::class)->heuristic_fallback);
    }

    public function test_secrets_are_encrypted_at_rest_and_decrypted_on_load(): void
    {
        $proxy = app(ProxySettings::class);
        $proxy->api_key = 'sk-live-abcdefghijklmnopqrstuvwxyz';
        $proxy->save();

        $keyPayload = DB::table('settings')->where('group', 'proxy')->where('name', 'api_key')->value('payload');

        $this->assertStringNotContainsString('sk-live-', (string) $keyPayload);
        $this->assertStringContainsString('eyJpdiI', (string) $keyPayload);
        $this->assertSame('sk-live-abcdefghijklmnopqrstuvwxyz', app(ProxySettings::class)->refresh()->api_key);
    }

    public function test_access_token_is_encrypted(): void
    {
        $security = app(SecuritySettings::class);
        $security->access_token = 'nb-plain-token';
        $security->save();

        $payload = DB::table('settings')->where('group', 'security')->where('name', 'access_token')->value('payload');

        $this->assertStringNotContainsString('plain-token', (string) $payload);
        $this->assertSame('nb-plain-token', app(SecuritySettings::class)->refresh()->access_token);
    }
}
