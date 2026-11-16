<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Ставит скилл `/brain` на диск харнесса.
 *
 * Тонкий файл из `docs/skills` читает протокол с MCP-сервера, поэтому
 * копируется как есть: обновлять его руками на каждой машине не нужно.
 */
class BrainSkillCommand extends Command
{
    protected $signature = 'brain:skill
        {--path= : Корень каталога скиллов, внутри появится brain/SKILL.md (по умолчанию ~/.agents/skills)}
        {--force : Перезаписать уже установленный файл}';

    protected $description = 'Install the /brain skill file for a harness';

    private const SOURCE = 'docs/skills/brain/SKILL.md';

    public function handle(): int
    {
        $source = base_path(self::SOURCE);

        if (! is_file($source)) {
            $this->error('Не найден файл скилла: '.self::SOURCE.'.');

            return self::FAILURE;
        }

        $root = $this->skillsRoot();

        if ($root === null) {
            $this->error('Не удалось определить домашний каталог. Укажите --path=<корень скиллов>.');

            return self::FAILURE;
        }

        $target = $root.DIRECTORY_SEPARATOR.'brain'.DIRECTORY_SEPARATOR.'SKILL.md';

        if (is_file($target) && ! $this->option('force')) {
            $this->warn('Скилл уже установлен: '.$target.'. Перезапишите его с --force.');

            return self::SUCCESS;
        }

        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);

        $this->info('Скилл /brain установлен: '.$target);
        $this->line('Для работы нужен MCP-сервер neiro-brain и личный токен из панели (/admin, раздел «Токены»).');

        return self::SUCCESS;
    }

    /**
     * Корень каталога скиллов: `--path=` или `~/.agents/skills`.
     */
    private function skillsRoot(): ?string
    {
        $path = $this->option('path');

        if (is_string($path) && trim($path) !== '') {
            return $this->trimSeparators(trim($path));
        }

        $home = $this->homeDirectory();

        if ($home === null) {
            return null;
        }

        return $home.DIRECTORY_SEPARATOR.'.agents'.DIRECTORY_SEPARATOR.'skills';
    }

    /**
     * Домашний каталог по-кросс-платформенному: на Windows это USERPROFILE,
     * на Unix — HOME. Переменные читаются из окружения и из $_SERVER.
     */
    private function homeDirectory(): ?string
    {
        $candidates = [
            $_SERVER['HOME'] ?? null,
            $_SERVER['USERPROFILE'] ?? null,
            getenv('HOME') ?: null,
            getenv('USERPROFILE') ?: null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $candidate = $this->trimSeparators(trim($candidate));

            if ($candidate !== '' && is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function trimSeparators(string $path): string
    {
        $trimmed = rtrim($path, '/\\');

        return $trimmed === '' ? $path : $trimmed;
    }
}
