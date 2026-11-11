<?php

declare(strict_types=1);

namespace App\Settings;

use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Spatie\LaravelSettings\Settings;
use Spatie\LaravelSettings\SettingsConfig;
use Spatie\LaravelSettings\Support\Crypto;

/**
 * spatie/laravel-settings only persists properties that exist in the settings
 * table. A missing row is loaded from the property default, and such an object
 * cannot be saved: the mapper throws MissingSettings instead of writing defaults.
 * That happens on a fresh install (empty table) and on an upgraded one, where the
 * table still holds rows of removed properties (security.api_token/mcp_token).
 *
 * This initializer adds rows for exactly the missing properties and leaves every
 * stored payload untouched, so an existing encrypted value is never re-encrypted.
 */
final class SettingsInitializer
{
    /** @var list<class-string<Settings>> */
    private const CLASSES = [
        AiSettings::class,
        RetrievalSettings::class,
        ProxySettings::class,
        SyncSettings::class,
        SecuritySettings::class,
    ];

    public function ensure(): void
    {
        foreach (self::CLASSES as $settingsClass) {
            $this->ensureClass($settingsClass);
        }
    }

    /**
     * @param  class-string<Settings>  $settingsClass
     */
    private function ensureClass(string $settingsClass): void
    {
        $defaults = $this->defaults($settingsClass);

        $stored = DB::table('settings')
            ->where('group', $settingsClass::group())
            ->pluck('name')
            ->all();

        $missing = array_values(array_diff(array_keys($defaults), $stored));

        if ($missing === []) {
            return;
        }

        $config = new SettingsConfig($settingsClass);
        $payloads = [];

        foreach ($missing as $name) {
            $payload = $defaults[$name];

            if ($cast = $config->getCast($name)) {
                $payload = $cast->set($payload);
            }

            if ($config->isEncrypted($name)) {
                $payload = Crypto::encrypt($payload);
            }

            $payloads[$name] = $payload;
        }

        $config->getRepository()->updatePropertiesPayload($settingsClass::group(), $payloads);
    }

    /**
     * @param  class-string<Settings>  $settingsClass
     * @return array<string, mixed>
     */
    private function defaults(string $settingsClass): array
    {
        $reflection = new ReflectionClass($settingsClass);
        $defaults = [];

        foreach ($reflection->getDefaultProperties() as $name => $value) {
            $property = $reflection->getProperty($name);

            if ($property->isPublic() && ! $property->isStatic()) {
                $defaults[$name] = $value;
            }
        }

        if ($settingsClass === ProxySettings::class) {
            $defaults['upstream'] = (string) config('brain.proxy.upstream', $defaults['upstream']);
            $defaults['base_url'] = (string) config('brain.proxy.base_url', $defaults['base_url']);
        }

        if ($settingsClass === SecuritySettings::class) {
            $defaults['access_token'] = (string) config('brain.tokens.all', $defaults['access_token']);
        }

        return $defaults;
    }
}
