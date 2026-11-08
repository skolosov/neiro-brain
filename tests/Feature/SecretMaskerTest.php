<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Brain\SecretMasker;
use App\Settings\SecuritySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretMaskerTest extends TestCase
{
    use RefreshDatabase;

    private SecretMasker $masker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->masker = app(SecretMasker::class);
    }

    public function test_it_masks_openai_and_anthropic_keys(): void
    {
        $result = $this->masker->mask('Ключи: sk-proj-abcdefghijklmnopqrstuvwxyz123456 и sk-ant-api03-abcdefghijklmnopqrstuvwxyz');

        $this->assertStringNotContainsString('sk-proj-', $result->text);
        $this->assertStringNotContainsString('sk-ant-', $result->text);
        $this->assertGreaterThanOrEqual(2, $result->total);
    }

    public function test_it_masks_env_assignments_and_database_urls(): void
    {
        $result = $this->masker->mask('DATABASE_PASSWORD=super-secret postgres://user:pass@localhost/db');

        $this->assertStringNotContainsString('super-secret', $result->text);
        $this->assertStringNotContainsString('pass@localhost', $result->text);
        $this->assertStringContainsString('DATABASE_PASSWORD=', $result->text);
    }

    public function test_it_masks_private_keys(): void
    {
        $key = "-----BEGIN RSA PRIVATE KEY-----\nMIIEowIBAAKCAQEA\n-----END RSA PRIVATE KEY-----";
        $result = $this->masker->mask($key);

        $this->assertStringNotContainsString('MIIEowIBAAKCAQEA', $result->text);
    }

    public function test_it_does_not_touch_ordinary_prose(): void
    {
        $text = 'Мы используем Laravel 13 и PostgreSQL для проекта Sklad.';
        $result = $this->masker->mask($text);

        $this->assertSame($text, $result->text);
        $this->assertSame(0, $result->total);
    }

    public function test_masking_can_be_disabled(): void
    {
        $settings = app(SecuritySettings::class);
        $settings->mask_secrets = false;
        $settings->save();

        $result = app(SecretMasker::class)->mask('sk-proj-abcdefghijklmnopqrstuvwxyz123456');

        $this->assertStringContainsString('sk-proj-', $result->text);
    }
}
