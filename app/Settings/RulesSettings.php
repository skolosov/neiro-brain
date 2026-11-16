<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Правила, которые владелец базы дописывает агенту сам.
 *
 * `instructions_extra` уходит в конец базового текста `Instructions`, который
 * MCP отдаёт при инициализации. Это не секрет, поэтому шифрование не нужно.
 */
class RulesSettings extends Settings
{
    public ?string $instructions_extra = null;

    public ?string $skill_note = null;

    /**
     * Редактируемый протокол агента. Пусто — сервер берёт `resources/brain/skill.md`.
     */
    public ?string $skill_text = null;

    public static function group(): string
    {
        return 'rules';
    }
}
