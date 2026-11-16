<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Prompts\AboutMePrompt;
use App\Mcp\Resources\KnowledgeEntryResource;
use App\Mcp\Resources\KnowledgeIndexResource;
use App\Mcp\Resources\SkillResource;
use App\Mcp\Tools\DeleteKnowledgeTool;
use App\Mcp\Tools\GetKnowledgeTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ProjectContextTool;
use App\Mcp\Tools\RecentKnowledgeTool;
use App\Mcp\Tools\RememberManyTool;
use App\Mcp\Tools\SearchKnowledgeTool;
use App\Services\Brain\InstructionsBuilder;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;

#[Name('neiro-brain')]
#[Version('1.0.0')]
class KnowledgeServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ProjectContextTool::class,
        SearchKnowledgeTool::class,
        GetKnowledgeTool::class,
        RememberManyTool::class,
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
        SkillResource::class,
    ];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        AboutMePrompt::class,
    ];

    /**
     * Текст правил собирается на каждый запрос: имя, язык и проекты зависят от
     * владельца из токена. Атрибут `#[Instructions]` здесь не используется —
     * единственный источник текста это InstructionsBuilder.
     */
    public function createContext(): ServerContext
    {
        $context = parent::createContext();
        $context->instructions = app(InstructionsBuilder::class)->build();

        return $context;
    }
}
