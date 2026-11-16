<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Mcp\Prompts\AboutMePrompt;
use App\Mcp\Servers\KnowledgeServer;
use App\Mcp\Tools\DeleteKnowledgeTool;
use App\Mcp\Tools\GetKnowledgeTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\RememberManyTool;
use App\Mcp\Tools\SearchKnowledgeTool;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\AssertableJson;
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
        );

        return $result['entry']->id;
    }

    private function secondOwner(): User
    {
        return User::query()->create([
            'name' => 'Второй владелец',
            'email' => 'second@neiro-brain.test',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_server_registers_remember_many_and_drops_remember_knowledge(): void
    {
        $issued = $this->issueToken($this->owner(), ['read', 'write']);

        // tools/list сериализует схему каждого инструмента: заодно проверяем,
        // что вложенная схема пачки строится без ошибок.
        $response = $this->postJson('/mcp/knowledge', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ], $this->mcpHeaders($issued['plain']))->assertSuccessful();

        /** @var array<int, array<string, mixed>> $tools */
        $tools = $response->json('result.tools');
        $names = array_column($tools, 'name');

        $this->assertContains('remember_many', $names);
        $this->assertNotContains('remember_knowledge', $names);

        $remember = collect($tools)->firstWhere('name', 'remember_many');
        $this->assertIsArray($remember);
        $this->assertSame('array', $remember['inputSchema']['properties']['items']['type']);
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

    public function test_remember_many_writes_a_batch_of_three_cards(): void
    {
        $response = KnowledgeServer::tool(RememberManyTool::class, [
            'items' => [
                [
                    'title' => 'Стек neiro-brain',
                    'content' => 'Стек проекта: Laravel 13, PostgreSQL, MoonShine, Vue 3.',
                    'kind' => 'stack',
                    'project' => 'neiro-brain',
                    'theme' => 'архитектура',
                    'tags' => ['laravel', 'php'],
                    'importance' => 4,
                ],
                [
                    'title' => 'Окружение на Windows',
                    'content' => 'Проекты лежат в C:\OSPanel65\home, PHP берётся из OSPanel.',
                    'kind' => 'process',
                    'scope' => 'environment',
                    'theme' => 'инфраструктура',
                ],
                [
                    'title' => 'Стиль тестов',
                    'content' => 'Пользователь пишет тесты на PHPUnit с RefreshDatabase.',
                    'kind' => 'preference',
                    'scope' => 'profile',
                    'theme' => 'тесты',
                    'tags' => ['phpunit', 'laravel'],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertSee('создано 3')
            ->assertSee('обновлено 0')
            ->assertSee('отклонено 0')
            ->assertStructuredContent(function (AssertableJson $json): void {
                $json->where('summary.created', 3)
                    ->where('summary.updated', 0)
                    ->where('summary.rejected', 0)
                    ->where('items.0.status', 'created')
                    ->etc();
            });

        $this->assertDatabaseCount('knowledge_entries', 3);
        $this->assertDatabaseHas('knowledge_entries', [
            'title' => 'Стек neiro-brain',
            'theme' => 'architecture',
            'project' => 'neiro-brain',
        ]);
        $this->assertDatabaseHas('knowledge_entries', [
            'title' => 'Окружение на Windows',
            'theme' => 'infra',
            'scope' => 'environment',
            'project' => null,
        ]);
        $this->assertDatabaseHas('knowledge_entries', [
            'title' => 'Стиль тестов',
            'theme' => 'tests',
            'scope' => 'profile',
        ]);

        // Без summary в ответе остаются первые 300 символов content.
        $card = KnowledgeEntry::query()->where('title', 'Стиль тестов')->firstOrFail();
        $this->assertSame($card->content, $card->summary);

        $search = KnowledgeServer::tool(SearchKnowledgeTool::class, ['query' => 'PHPUnit RefreshDatabase']);
        $search->assertOk()->assertSee('Стиль тестов');
    }

    public function test_duplicate_inside_batch_is_reported_as_updated(): void
    {
        $response = KnowledgeServer::tool(RememberManyTool::class, [
            'items' => [
                [
                    'title' => 'Первый заход',
                    'content' => 'Один и тот же текст, записанный дважды в одной пачке.',
                ],
                [
                    'title' => 'Второй заход',
                    'content' => 'Один и тот же текст, записанный дважды в одной пачке.',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertSee('создано 1')
            ->assertSee('обновлено 1')
            ->assertSee('отклонено 0')
            ->assertStructuredContent(function (AssertableJson $json): void {
                $json->where('summary.created', 1)
                    ->where('summary.updated', 1)
                    ->where('items.0.status', 'created')
                    ->where('items.1.status', 'updated')
                    ->etc();
            });

        $this->assertDatabaseCount('knowledge_entries', 1);
    }

    public function test_card_with_empty_content_is_rejected_while_others_are_written(): void
    {
        $response = KnowledgeServer::tool(RememberManyTool::class, [
            'items' => [
                [
                    'title' => 'Хорошая карточка',
                    'content' => 'Эта карточка должна записаться.',
                ],
                [
                    'title' => 'Пустая карточка',
                    'content' => '',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertSee('создано 1')
            ->assertSee('отклонено 1')
            ->assertSee('Пустая карточка')
            ->assertSee('нужен content')
            ->assertStructuredContent(function (AssertableJson $json): void {
                $json->where('summary.rejected', 1)
                    ->where('items.1.status', 'rejected')
                    ->etc();
            });

        $this->assertDatabaseCount('knowledge_entries', 1);
        $this->assertDatabaseHas('knowledge_entries', ['title' => 'Хорошая карточка']);
        $this->assertDatabaseMissing('knowledge_entries', ['title' => 'Пустая карточка']);
    }

    public function test_empty_batch_returns_an_error(): void
    {
        $response = KnowledgeServer::tool(RememberManyTool::class, ['items' => []]);

        $response->assertHasErrors(['Пачка пустая']);
    }

    public function test_supersedes_marks_the_old_card_and_hides_it_from_search(): void
    {
        $oldId = $this->seedEntry();

        $response = KnowledgeServer::tool(RememberManyTool::class, [
            'items' => [[
                'title' => 'Стек после обновления',
                'content' => 'Проект использует Laravel 13, PostgreSQL, MoonShine и Vue 3, плюс Redis.',
                'kind' => 'stack',
                'project' => 'neiro-brain',
                'supersedes' => [$oldId],
            ]],
        ]);

        $response->assertOk()->assertSee('Заменены: #'.$oldId);

        $new = KnowledgeEntry::query()->where('title', 'Стек после обновления')->firstOrFail();
        $old = KnowledgeEntry::withHistory(
            fn (): ?KnowledgeEntry => KnowledgeEntry::query()->find($oldId),
        );

        $this->assertNotNull($old);
        $this->assertSame((int) $new->id, (int) $old->superseded_by);

        // Устаревшая карточка не видна ни в обычной выборке, ни в поиске.
        $this->assertNull(KnowledgeEntry::query()->find($oldId));

        $search = KnowledgeServer::tool(SearchKnowledgeTool::class, ['query' => 'Laravel PostgreSQL MoonShine']);
        $search->assertOk()->assertSee('Стек после обновления')->assertDontSee('Основной стек');
    }

    public function test_supersedes_ignores_foreign_card(): void
    {
        $this->withOwner($this->secondOwner());
        $foreignId = $this->seedEntry();

        $this->withOwner($this->owner());

        $response = KnowledgeServer::tool(RememberManyTool::class, [
            'items' => [[
                'title' => 'Своя карточка',
                'content' => 'Эта карточка принадлежит текущему владельцу.',
                'supersedes' => [$foreignId],
            ]],
        ]);

        $response->assertOk()
            ->assertSee('создано 1')
            ->assertSee('Пропущены чужие или несуществующие: #'.$foreignId);

        $foreign = KnowledgeEntry::query()
            ->withoutGlobalScope('owner')
            ->find($foreignId);

        $this->assertNotNull($foreign);
        $this->assertNull($foreign->superseded_by);
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
