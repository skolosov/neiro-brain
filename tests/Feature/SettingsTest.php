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

    public function test_initializer_adds_a_missing_property_next_to_legacy_rows(): void
    {
        DB::table('settings')->where('group', 'security')->where('name', 'mask_emails')->delete();

        $now = now();

        foreach (['api_token', 'mcp_token'] as $legacy) {
            DB::table('settings')->insert([
                'group' => 'security',
                'name' => $legacy,
                'locked' => false,
                'payload' => json_encode('stale', JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        app(SettingsInitializer::class)->ensure();

        $this->assertDatabaseHas('settings', ['group' => 'security', 'name' => 'mask_emails']);

        $security = app(SecuritySettings::class)->refresh();
        $security->mask_emails = true;
        $security->save();

        $this->assertTrue(app(SecuritySettings::class)->refresh()->mask_emails);
    }

    public function test_initializer_does_not_re_encrypt_existing_values(): void
    {
        $proxy = app(ProxySettings::class);
        $proxy->api_key = 'sk-existing-secret';
        $proxy->save();

        DB::table('settings')->where('group', 'proxy')->where('name', 'top_k')->delete();

        app(SettingsInitializer::class)->ensure();

        $this->assertSame('sk-existing-secret', app(ProxySettings::class)->refresh()->api_key);
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
}
