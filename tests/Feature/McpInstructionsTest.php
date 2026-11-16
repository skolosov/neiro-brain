<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mcp\Servers\KnowledgeServer;
use App\Models\User;
use App\Services\Brain\InstructionsBuilder;
use App\Settings\RulesSettings;
use App\Support\OwnerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\TestCase;

/**
 * `instructions` — правила плюс медленный контекст владельца.
 *
 * Текст собирается на каждый запрос из OwnerContext: два человека получают
 * разные правила, а без владельца сервер отдаёт только базовую часть.
 */
class McpInstructionsTest extends TestCase
{
    use RefreshDatabase;

    private const MCP_INITIALIZE = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [],
    ];

    private function initialize(string $plain): string
    {
        $response = $this->postJson(
            '/mcp/knowledge',
            self::MCP_INITIALIZE,
            $this->mcpHeaders($plain),
        )->assertSuccessful();

        $instructions = $response->json('result.instructions');

        $this->assertIsString($instructions);

        return $instructions;
    }

    public function test_initialize_returns_instructions_with_owner_name_and_locale(): void
    {
        $owner = $this->owner();
        $owner->forceFill(['name' => 'Анна', 'locale' => 'ru'])->save();
        $issued = $this->issueToken($owner, ['read', 'write']);

        $instructions = $this->initialize($issued['plain']);

        $this->assertStringContainsString('Имя: Анна', $instructions);
        $this->assertStringContainsString('Язык: ru', $instructions);
        $this->assertStringContainsString('search_knowledge', $instructions);
        $this->assertStringContainsString('remember_many', $instructions);
        $this->assertStringContainsString('supersedes', $instructions);
        $this->assertStringStartsWith(InstructionsBuilder::BASE, $instructions);
    }

    public function test_instructions_differ_between_users(): void
    {
        $first = $this->owner();
        $first->forceFill(['name' => 'Первый', 'locale' => 'ru'])->save();

        $second = User::query()->create([
            'name' => 'Пётр',
            'email' => 'second@neiro-brain.test',
            'password' => Hash::make('password'),
            'locale' => 'en',
        ]);

        $firstText = $this->initialize($this->issueToken($first)['plain']);
        $secondText = $this->initialize($this->issueToken($second)['plain']);

        $this->assertNotSame($firstText, $secondText);
        $this->assertStringContainsString('Имя: Первый', $firstText);
        $this->assertStringContainsString('Язык: ru', $firstText);
        $this->assertStringContainsString('Имя: Пётр', $secondText);
        $this->assertStringContainsString('Язык: en', $secondText);
        $this->assertStringNotContainsString('Имя: Пётр', $firstText);
    }

    public function test_extra_rules_from_settings_reach_mcp_instructions(): void
    {
        $settings = app(RulesSettings::class);
        $settings->instructions_extra = '## Правила владельца'."\n".'- Всегда отвечай кратко и по-русски.';
        $settings->save();

        $owner = $this->owner();
        $issued = $this->issueToken($owner, ['read', 'write']);

        $instructions = $this->initialize($issued['plain']);

        $this->assertStringContainsString('Всегда отвечай кратко и по-русски.', $instructions);
        $this->assertStringContainsString('## Правила владельца', $instructions);
        $this->assertStringStartsWith(InstructionsBuilder::BASE, $instructions);
    }

    public function test_empty_context_returns_only_the_base_text(): void
    {
        app(OwnerContext::class)->forget();

        $server = $this->app->make(KnowledgeServer::class, ['transport' => new FakeTransporter]);

        $this->assertSame(InstructionsBuilder::BASE, $server->createContext()->instructions);
    }
}
