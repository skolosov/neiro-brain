<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mcp\Servers\KnowledgeServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\TestCase;

/**
 * Инварианты скилла `/brain`: текст не расходится с кодом сервера, не упоминает
 * удалённое, а команда установки копирует файл и не затирает чужую версию.
 *
 * Тесты не трогают домашний каталог: установка идёт только в --path.
 */
class SkillTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Инструменты, по которым ищутся упоминания в тексте скилла.
     *
     * Список намеренно задан вручную: если инструмент переименуют, старое имя
     * останется в тексте, не найдётся в KnowledgeServer и тест покраснеет.
     *
     * @var list<string>
     */
    private const TOOLS = [
        'get_project_context',
        'search_knowledge',
        'get_knowledge',
        'remember_many',
        'delete_knowledge',
        'list_projects',
        'recent_knowledge',
    ];

    /**
     * Строки удалённых возможностей: в скилле их быть не должно.
     *
     * @var list<string>
     */
    private const REMOVED = [
        'proxy',
        'прокси',
        '/v1/',
        'remember_knowledge',
        'brain:sync',
    ];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }

    public function test_every_tool_named_in_the_skill_exists_in_the_server(): void
    {
        $skill = $this->skillText();
        $registered = $this->registeredTools();

        $mentioned = array_values(array_filter(
            self::TOOLS,
            fn (string $tool): bool => str_contains($skill, $tool),
        ));

        $this->assertNotSame([], $mentioned, 'Скилл не упоминает ни один инструмент.');

        foreach ($mentioned as $tool) {
            $this->assertContains(
                $tool,
                $registered,
                sprintf('Скилл упоминает инструмент %s, которого нет в KnowledgeServer.', $tool),
            );
        }
    }

    public function test_every_resource_uri_named_in_the_skill_is_registered(): void
    {
        $text = $this->skillText()."\n".$this->installedSkillText();

        preg_match_all('~knowledge://[a-z0-9/._\-{}]+~i', $text, $matches);

        $mentioned = array_values(array_unique($matches[0]));

        $this->assertNotSame([], $mentioned, 'Скилл не упоминает ни один ресурс knowledge://.');
        $this->assertContains('knowledge://skill/brain', $mentioned);

        $registered = $this->registeredResourceUris();

        foreach ($mentioned as $uri) {
            $this->assertContains(
                $uri,
                $registered,
                sprintf('Скилл упоминает ресурс %s, которого нет в KnowledgeServer.', $uri),
            );
        }
    }

    public function test_skill_does_not_mention_removed_features(): void
    {
        $text = mb_strtolower($this->skillText()."\n".$this->installedSkillText());

        foreach (self::REMOVED as $removed) {
            $this->assertStringNotContainsString(
                $removed,
                $text,
                sprintf('Скилл упоминает удалённое: %s.', $removed),
            );
        }
    }

    public function test_command_installs_the_skill_into_the_given_directory(): void
    {
        $root = $this->temporaryDirectory();
        $target = $root.DIRECTORY_SEPARATOR.'brain'.DIRECTORY_SEPARATOR.'SKILL.md';

        $this->artisan('brain:skill', ['--path' => $root])
            ->expectsOutputToContain($target)
            ->assertSuccessful();

        $this->assertFileExists($target);
        $this->assertSame(File::get($this->installedSkillPath()), File::get($target));
    }

    public function test_command_does_not_overwrite_without_force(): void
    {
        $root = $this->temporaryDirectory();
        $target = $root.DIRECTORY_SEPARATOR.'brain'.DIRECTORY_SEPARATOR.'SKILL.md';

        $this->artisan('brain:skill', ['--path' => $root])->assertSuccessful();
        File::put($target, 'своя версия');

        $this->artisan('brain:skill', ['--path' => $root])
            ->expectsOutputToContain('уже установлен')
            ->assertSuccessful();

        $this->assertSame('своя версия', File::get($target));
    }

    public function test_force_overwrites_an_existing_skill(): void
    {
        $root = $this->temporaryDirectory();
        $target = $root.DIRECTORY_SEPARATOR.'brain'.DIRECTORY_SEPARATOR.'SKILL.md';

        File::ensureDirectoryExists(dirname($target));
        File::put($target, 'своя версия');

        $this->artisan('brain:skill', ['--path' => $root, '--force' => true])
            ->expectsOutputToContain('установлен')
            ->assertSuccessful();

        $installed = File::get($target);

        $this->assertSame(File::get($this->installedSkillPath()), $installed);
        $this->assertStringContainsString('name: brain', $installed);
        $this->assertStringContainsString('knowledge://skill/brain', $installed);
    }

    private function skillText(): string
    {
        $path = resource_path('brain/skill.md');

        $this->assertFileExists($path);

        return File::get($path);
    }

    private function installedSkillPath(): string
    {
        return base_path('docs/skills/brain/SKILL.md');
    }

    private function installedSkillText(): string
    {
        $path = $this->installedSkillPath();

        $this->assertFileExists($path);

        return File::get($path);
    }

    /**
     * @return list<string>
     */
    private function registeredTools(): array
    {
        $context = $this->app->make(KnowledgeServer::class, ['transport' => new FakeTransporter])
            ->createContext();

        return $context->tools()
            ->map(fn (Tool $tool): string => $tool->name())
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function registeredResourceUris(): array
    {
        $context = $this->app->make(KnowledgeServer::class, ['transport' => new FakeTransporter])
            ->createContext();

        return $context->resources()
            ->merge($context->resourceTemplates())
            ->map(fn (Resource $resource): string => $resource->uri())
            ->values()
            ->all();
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'brain-skill-'.bin2hex(random_bytes(6));

        $this->temporaryDirectories[] = $directory;

        return $directory;
    }
}
