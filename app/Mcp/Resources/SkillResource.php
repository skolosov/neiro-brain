<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

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
 * Файл читается на каждом запросе без кэша: правила меняются вместе с кодом,
 * и устаревшая копия в памяти учила бы агента старому поведению.
 */
#[Name('brain-skill')]
#[Uri('knowledge://skill/brain')]
#[MimeType('text/markdown')]
#[Description('Правила работы с базой знаний: когда искать, как записывать карточки, как сливать дубли через remember_many и supersedes.')]
class SkillResource extends Resource
{
    public function handle(Request $request): Response
    {
        $path = resource_path('brain/skill.md');

        if (! is_file($path)) {
            return Response::error('Файл правил не найден: resources/brain/skill.md.');
        }

        $markdown = file_get_contents($path);

        if ($markdown === false) {
            return Response::error('Не удалось прочитать файл правил: resources/brain/skill.md.');
        }

        return Response::text($markdown);
    }
}
