<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * Публичная раздача скилла `/brain` и скриптов его установки.
 *
 * Авторизации нет намеренно: без этого первый шаг невыполним — человеку негде
 * взять токен, пока он не поставил скилл и не зашёл в админку. Секрета в файле
 * нет: это инструкция агенту, а не данные пользователя. Доступ к знаниям
 * по-прежнему закрыт личным токеном на `/mcp/knowledge`.
 */
final class SkillController extends Controller
{
    private const SKILL_PATH = 'docs/skills/brain/SKILL.md';

    /**
     * PowerShell: кладёт skill в %USERPROFILE%\.agents\skills\brain.
     */
    private const INSTALL_PS1 = <<<'PS1'
# Установка скилла /brain для Neiro Brain.
# Скрипт делает одно: создаёт каталог скиллов и скачивает в него SKILL.md.
$ErrorActionPreference = 'Stop'
$base = '__BASE__'
$dir = Join-Path $env:USERPROFILE '.agents\skills\brain'
New-Item -ItemType Directory -Force -Path $dir | Out-Null
$target = Join-Path $dir 'SKILL.md'
Invoke-WebRequest -UseBasicParsing -Uri "$base/skill/brain" -OutFile $target
Write-Host "Скилл установлен: $target"
Write-Host ''
Write-Host "Дальше два шага:"
Write-Host "  1. Выпустите личный токен в админке ($base/admin), раздел «Токены»."
Write-Host "  2. В харнессе наберите /brain init — он подключит MCP-сервер."
PS1;

    /**
     * sh: то же самое для macOS и Linux.
     */
    private const INSTALL_SH = <<<'SH'
#!/bin/sh
# Установка скилла /brain для Neiro Brain.
# Скрипт делает одно: создаёт каталог скиллов и скачивает в него SKILL.md.
set -eu
base='__BASE__'
dir="${HOME}/.agents/skills/brain"
mkdir -p "$dir"
curl -fsSL "$base/skill/brain" -o "$dir/SKILL.md"
printf 'Скилл установлен: %s/SKILL.md\n\n' "$dir"
printf 'Дальше два шага:\n'
printf '  1. Выпустите личный токен в админке (%s/admin), раздел «Токены».\n' "$base"
printf '  2. В харнессе наберите /brain init — он подключит MCP-сервер.\n'
SH;

    /**
     * Тонкий файл скилла: его ставят на диск харнесса.
     */
    public function skill(): Response
    {
        $path = base_path(self::SKILL_PATH);

        abort_unless(is_file($path), 404, 'Файл скилла не найден на сервере.');

        return response(File::get($path), 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function installPs1(Request $request): Response
    {
        return $this->script(self::INSTALL_PS1, $request, 'text/plain; charset=utf-8');
    }

    public function installSh(Request $request): Response
    {
        return $this->script(self::INSTALL_SH, $request, 'text/x-shellscript; charset=utf-8');
    }

    private function script(string $template, Request $request, string $mime): Response
    {
        // Хост берём из запроса: скрипт всегда ставит тот сервер, с которого его скачали.
        $script = str_replace('__BASE__', rtrim($request->getSchemeAndHttpHost(), '/'), $template);

        return response($script, 200, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
