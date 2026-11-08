<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use App\Services\Brain\ProfileBuilder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;

#[Name('about-me')]
#[Description('Готовый системный контекст о пользователе: профиль, предпочтения, компания, стек и стиль работы.')]
class AboutMePrompt extends Prompt
{
    public function __construct(
        private readonly ProfileBuilder $profile,
    ) {}

    public function handle(Request $request): Response
    {
        return Response::text($this->profile->build(8000));
    }
}
