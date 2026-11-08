<?php

declare(strict_types=1);

namespace App\Settings;

use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Spatie\LaravelSettings\Settings;

/**
 * spatie/laravel-settings only persists properties that exist in the settings
 * table. On a fresh install the table is empty, so the first save from the admin
 * panel would fail with MissingSettings. This initializer writes the declared
 * defaults once, preserving any values that already exist.
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
        $reflection = new ReflectionClass($settingsClass);
        $defaults = [];

        foreach ($reflection->getDefaultProperties() as $name => $value) {
            $property = $reflection->getProperty($name);

            if ($property->isPublic() && ! $property->isStatic()) {
                $defaults[$name] = $value;
            }
        }

        $rows = DB::table('settings')->where('group', $settingsClass::group())->pluck('payload', 'name');
        $stored = [];

        foreach ($rows as $name => $payload) {
            $stored[$name] = json_decode((string) $payload, true);
        }

        if (count($stored) >= count($defaults)) {
            return;
        }

        $settingsClass::fake(array_merge($defaults, $stored))->save();
    }
}
