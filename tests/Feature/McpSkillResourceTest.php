<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mcp\Resources\SkillResource;
use App\Mcp\Servers\KnowledgeServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ресурс `knowledge://skill/brain` отдаёт тот же markdown, что лежит на диске.
 */
class McpSkillResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_skill_resource_returns_markdown_from_file(): void
    {
        $issued = $this->issueToken($this->owner(), ['read', 'write']);

        $response = $this->postJson('/mcp/knowledge', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'resources/read',
            'params' => ['uri' => 'knowledge://skill/brain'],
        ], $this->mcpHeaders($issued['plain']))->assertSuccessful();

        $expected = file_get_contents(resource_path('brain/skill.md'));

        $this->assertIsString($expected);
        $this->assertSame($expected, $response->json('result.contents.0.text'));
        $this->assertSame('text/markdown', $response->json('result.contents.0.mimeType'));
        $this->assertStringContainsString('remember_many', $expected);
        $this->assertStringContainsString('supersedes', $expected);
    }

    public function test_skill_resource_declares_uri_and_mime_type(): void
    {
        $resource = new SkillResource;

        $this->assertSame('knowledge://skill/brain', $resource->uri());
        $this->assertSame('text/markdown', $resource->mimeType());
    }

    public function test_skill_resource_is_registered_in_the_server(): void
    {
        KnowledgeServer::resources()->assertRegistered(SkillResource::class);
    }
}
