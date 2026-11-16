<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Публичная раздача скилла и скриптов установки.
 *
 * Без этих роутов первый шаг невыполним: пока скилл не стоит, человек не знает
 * ни про кабинет, ни про токен. Поэтому проверяем и доступ без токена, и то,
 * что скрипты ставят файл именно туда, где харнесс его ищет.
 */
class SkillBootstrapTest extends TestCase
{
    public function test_skill_file_is_served_without_a_token(): void
    {
        $response = $this->get('/skill/brain');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/markdown; charset=utf-8');

        $text = $response->getContent();

        $this->assertIsString($text);
        $this->assertStringContainsString('name: brain', $text);
        $this->assertStringContainsString('/brain init', $text);
        $this->assertStringContainsString('/brain doctor', $text);
    }

    public function test_powershell_installer_targets_the_windows_skills_directory(): void
    {
        $response = $this->get('/skill/install.ps1');

        $response->assertOk();

        $script = (string) $response->getContent();

        $this->assertStringContainsString('.agents\skills\brain', $script);
        $this->assertStringContainsString('/skill/brain', $script);
        $this->assertStringContainsString('USERPROFILE', $script);
        $this->assertStringContainsString($this->baseUrl(), $script);
    }

    public function test_shell_installer_works_on_macos_and_linux(): void
    {
        $response = $this->get('/skill/install.sh');

        $response->assertOk();

        $script = (string) $response->getContent();

        $this->assertStringContainsString('.agents/skills/brain', $script);
        $this->assertStringContainsString('${HOME}', $script);
        $this->assertStringContainsString('curl -fsSL', $script);
        $this->assertStringContainsString($this->baseUrl(), $script);
    }

    public function test_installers_send_the_host_they_were_downloaded_from(): void
    {
        $response = $this->get('http://brain.example.ru/skill/install.sh');

        $response->assertOk();
        $this->assertStringContainsString(
            'http://brain.example.ru',
            (string) $response->getContent(),
        );
        $this->assertStringNotContainsString('__BASE__', (string) $response->getContent());
    }

    public function test_both_skill_copies_document_init_and_doctor(): void
    {
        foreach ([resource_path('brain/skill.md'), base_path('docs/skills/brain/SKILL.md')] as $path) {
            $this->assertFileExists($path);
            $text = File::get($path);

            $this->assertStringContainsString('/brain init', $text, $path);
            $this->assertStringContainsString('/brain doctor', $text, $path);
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
