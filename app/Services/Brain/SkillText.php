<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Settings\RulesSettings;
use App\Settings\SettingsInitializer;

/**
 * Текст протокола для агента: настройка `rules.skill_text` или файл-эталон.
 *
 * Файл `resources/brain/skill.md` остаётся в репозитории значением по умолчанию
 * и точкой сброса. Пока настройка пуста, сервер отдаёт файл; как только админ
 * сохранил свой текст, отдаётся он. Кэша нет: правка в админке должна быть
 * видна следующему же запросу MCP-ресурса.
 */
final class SkillText
{
    public const DEFAULT_PATH = 'brain/skill.md';

    public function current(): string
    {
        // На обновлённой базе строки `skill_text` может ещё не быть: без этого
        // spatie бросает MissingSettings на первом же чтении настройки.
        app(SettingsInitializer::class)->ensure();

        $stored = trim((string) app(RulesSettings::class)->skill_text);

        return $stored !== '' ? $stored : $this->default();
    }

    public function default(): string
    {
        $path = resource_path(self::DEFAULT_PATH);

        if (! is_file($path)) {
            return '';
        }

        $text = file_get_contents($path);

        return $text === false ? '' : $text;
    }

    public function reset(): void
    {
        app(SettingsInitializer::class)->ensure();

        $settings = app(RulesSettings::class);
        $settings->skill_text = null;
        $settings->save();
    }
}
