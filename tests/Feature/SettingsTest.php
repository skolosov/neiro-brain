<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Settings\RetrievalSettings;
use App\Settings\RulesSettings;
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

        foreach (['retrieval', 'security', 'rules'] as $group) {
            $this->assertGreaterThan(0, DB::table('settings')->where('group', $group)->count(), $group);
        }

        $this->assertSame(1.2, app(RetrievalSettings::class)->k1);
        $this->assertNull(app(RulesSettings::class)->instructions_extra);
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

    public function test_initializer_does_not_overwrite_existing_values(): void
    {
        $rules = app(RulesSettings::class);
        $rules->instructions_extra = 'Сохранить это правило.';
        $rules->save();

        DB::table('settings')->where('group', 'retrieval')->where('name', 'top_k')->delete();

        app(SettingsInitializer::class)->ensure();

        $this->assertSame('Сохранить это правило.', app(RulesSettings::class)->refresh()->instructions_extra);
        $this->assertDatabaseHas('settings', ['group' => 'retrieval', 'name' => 'top_k']);
    }

    public function test_rules_are_stored_as_plain_text_not_encrypted(): void
    {
        $rules = app(RulesSettings::class);
        $rules->instructions_extra = 'Не секрет, а правило для агента.';
        $rules->save();

        $payload = DB::table('settings')->where('group', 'rules')->where('name', 'instructions_extra')->value('payload');

        $this->assertSame('Не секрет, а правило для агента.', json_decode((string) $payload, true));
        $this->assertSame('Не секрет, а правило для агента.', app(RulesSettings::class)->refresh()->instructions_extra);
    }
}
