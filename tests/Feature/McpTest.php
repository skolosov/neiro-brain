<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Mcp\Prompts\AboutMePrompt;
use App\Mcp\Servers\KnowledgeServer;
use App\Mcp\Tools\DeleteKnowledgeTool;
use App\Mcp\Tools\GetKnowledgeTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\RememberKnowledgeTool;
use App\Mcp\Tools\SearchKnowledgeTool;
use App\Models\KnowledgeChunk;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Services\Brain\SourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpTest extends TestCase
{
    use RefreshDatabase;

    private function seedEntry(): int
    {
        $result = app(KnowledgeWriter::class)->write(
            new ExtractedEntry(
                kind: KnowledgeKind::Stack,
                title: 'Основной стек',
                content: 'Основной стек: Laravel 13, PostgreSQL, MoonShine, Vue 3.',
                project: 'neiro-brain',
                importance: 4,
                confidence: 1.0,
            ),
            source: app(SourceRegistry::class)->manual(),
        );

        return $result['entry']->id;
    }

    public function test_search_tool_returns_markdown_and_structured_content(): void
    {
        $this->seedEntry();

        $response = KnowledgeServer::tool(SearchKnowledgeTool::class, [
            'query' => 'Какой стек?',
            'limit' => 3,
        ]);

        $response->assertOk()->assertSee('Основной стек');
    }

    public function test_get_knowledge_tool_returns_full_entry(): void
    {
        $id = $this->seedEntry();

        $response = KnowledgeServer::tool(GetKnowledgeTool::class, ['id' => $id]);

        $response->assertOk()->assertSee('PostgreSQL')->assertSee('neiro-brain');
    }

    public function test_remember_tool_writes_and_indexes_a_fact(): void
    {
        $response = KnowledgeServer::tool(RememberKnowledgeTool::class, [
            'title' => 'Предпочтение по тестам',
            'content' => 'Пользователь пишет тесты на Pest.',
            'kind' => 'preference',
            'project' => 'neiro-brain',
            'importance' => 4,
        ]);

        $response->assertOk()->assertSee('Создана');

        $this->assertDatabaseHas('knowledge_entries', [
            'title' => 'Предпочтение по тестам',
            'kind' => 'preference',
        ]);
        $this->assertTrue(
            KnowledgeChunk::query()
                ->where('content', 'like', '%Пользователь пишет тесты на Pest.%')
                ->exists(),
        );

        $search = KnowledgeServer::tool(SearchKnowledgeTool::class, ['query' => 'Pest тесты']);
        $search->assertOk()->assertSee('Pest');
    }

    public function test_remember_tool_stores_environment_scope(): void
    {
        $response = KnowledgeServer::tool(RememberKnowledgeTool::class, [
            'title' => 'Окружение на Mac',
            'content' => 'Пользователь работает на Mac, проекты лежат в /Users/skolosov/Desktop/www.',
            'kind' => 'process',
            'scope' => 'environment',
            'importance' => 5,
        ]);

        $response->assertOk()->assertSee('Создана');

        $this->assertDatabaseHas('knowledge_entries', [
            'title' => 'Окружение на Mac',
            'scope' => 'environment',
            'project' => null,
        ]);
    }

    public function test_delete_tool_archives_by_default_and_hides_card_from_search(): void
    {
        $id = $this->seedEntry();

        $response = KnowledgeServer::tool(DeleteKnowledgeTool::class, ['ids' => [$id]]);

        $response->assertOk()->assertSee('архив');
        $this->assertDatabaseHas('knowledge_entries', ['id' => $id, 'status' => 'archived']);

        $search = KnowledgeServer::tool(SearchKnowledgeTool::class, ['query' => 'основной стек Laravel']);
        $search->assertOk()->assertDontSee('Основной стек');
    }

    public function test_delete_tool_removes_card_and_its_index(): void
    {
        $id = $this->seedEntry();

        $response = KnowledgeServer::tool(DeleteKnowledgeTool::class, [
            'ids' => [$id],
            'mode' => 'delete',
        ]);

        $response->assertOk()->assertSee('Удалено');
        $this->assertDatabaseMissing('knowledge_entries', ['id' => $id]);
        $this->assertFalse(
            KnowledgeChunk::query()->where('entry_id', $id)->exists(),
        );
    }

    public function test_delete_tool_reports_missing_ids(): void
    {
        $response = KnowledgeServer::tool(DeleteKnowledgeTool::class, ['ids' => [999999]]);

        $response->assertHasErrors(['не найдены']);
    }

    public function test_projects_tool_lists_projects(): void
    {
        $this->seedEntry();

        $response = KnowledgeServer::tool(ListProjectsTool::class, []);

        $response->assertOk()->assertSee('neiro-brain');
    }

    public function test_about_me_prompt_returns_profile(): void
    {
        $this->seedEntry();

        $response = KnowledgeServer::prompt(AboutMePrompt::class);

        $response->assertOk()->assertSee('Основной стек');
    }

    public function test_mcp_endpoint_requires_token(): void
    {
        $this->postJson('/mcp/knowledge', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ], ['Accept' => 'application/json, text/event-stream'])
            ->assertUnauthorized();
    }
}
