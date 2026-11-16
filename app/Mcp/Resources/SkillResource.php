<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Services\Brain\SkillText;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

/**
 * Тело скилла `/brain`, которое харнесс читает вместо локальной копии.
 *
 * Текст берётся через `SkillText`: админ может отредактировать его в панели,
 * а файл `resources/brain/skill.md` остаётся значением по умолчанию и сбросом.
 * Кэша нет: правила меняются вместе с настройкой, и устаревшая копия в памяти
 * учила бы агента старому поведению.
 */
#[Name('brain-skill')]
#[Uri('knowledge://skill/brain')]
#[MimeType('text/markdown')]
#[Description('Правила работы с базой знаний: когда искать, как записывать карточки, как сливать дубли через remember_many и supersedes.')]
class SkillResource extends Resource
{
    public function handle(Request $request): Response
    {
        $markdown = app(SkillText::class)->current();

        if (trim($markdown) === '') {
            return Response::error('Текст правил пуст: настройка skill_text не задана, файл resources/brain/skill.md недоступен.');
        }

        return Response::text($markdown);
    }
}
