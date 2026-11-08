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
        $proxy->access_token = 'super-secret-proxy-token';
        $proxy->api_key = 'sk-live-abcdefghijklmnopqrstuvwxyz';
        $proxy->save();

        $tokenPayload = DB::table('settings')->where('group', 'proxy')->where('name', 'access_token')->value('payload');
        $keyPayload = DB::table('settings')->where('group', 'proxy')->where('name', 'api_key')->value('payload');

        $this->assertStringNotContainsString('super-secret-proxy-token', (string) $tokenPayload);
        $this->assertStringNotContainsString('sk-live-', (string) $keyPayload);
        $this->assertStringContainsString('eyJpdiI', (string) $tokenPayload);

        $reloaded = app(ProxySettings::class)->refresh();

        $this->assertSame('super-secret-proxy-token', $reloaded->access_token);
        $this->assertSame('sk-live-abcdefghijklmnopqrstuvwxyz', $reloaded->api_key);
    }

    public function test_mcp_and_api_tokens_are_encrypted(): void
    {
        $security = app(SecuritySettings::class);
        $security->mcp_token = 'mcp-plain-token';
        $security->api_token = 'api-plain-token';
        $security->save();

        foreach (['mcp_token', 'api_token'] as $name) {
            $payload = DB::table('settings')->where('group', 'security')->where('name', $name)->value('payload');

            $this->assertStringNotContainsString('plain-token', (string) $payload);
        }
    }
}
