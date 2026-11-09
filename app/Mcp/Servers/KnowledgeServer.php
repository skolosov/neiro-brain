<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Prompts\AboutMePrompt;
use App\Mcp\Resources\KnowledgeEntryResource;
use App\Mcp\Resources\KnowledgeIndexResource;
use App\Mcp\Tools\DeleteKnowledgeTool;
use App\Mcp\Tools\GetKnowledgeTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ProjectContextTool;
use App\Mcp\Tools\RecentKnowledgeTool;
use App\Mcp\Tools\RememberKnowledgeTool;
use App\Mcp\Tools\SearchKnowledgeTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('neiro-brain')]
#[Version('1.0.0')]
#[Instructions('Личная база знаний пользователя. Перед вопросами о пользователе, его компании, проектах, стеке, предпочтениях и прошлых решениях вызывай search_knowledge. Используй get_knowledge для полного текста, remember_knowledge — когда просят запомнить (укажи scope: profile, environment или project), delete_knowledge — когда просят убрать карточку.')]
class KnowledgeServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ProjectContextTool::class,
        SearchKnowledgeTool::class,
        GetKnowledgeTool::class,
        RememberKnowledgeTool::class,
        DeleteKnowledgeTool::class,
        ListProjectsTool::class,
        RecentKnowledgeTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        KnowledgeIndexResource::class,
        KnowledgeEntryResource::class,
    ];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        AboutMePrompt::class,
    ];
}
