<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Settings\SecuritySettings;
use App\Settings\SettingsInitializer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Показывает и меняет единственный токен доступа.
 *
 * Прокси, MCP и API проверяют одно значение, поэтому команда умеет только две
 * вещи: показать его и сгенерировать новое. `--prune` добивает строки старых
 * полей (proxy.access_token, security.mcp_token, security.api_token), которые
 * остались от прежней схемы с тремя токенами.
 */
class BrainTokenCommand extends Command
{
    protected $signature = 'brain:token
                            {--generate : Сгенерировать новый nb-… и сохранить его}
                            {--prune : Удалить из settings поля старых токенов}
                            {--force : Удалить старые поля, даже если действующий токен — из репозитория}';

    protected $description = 'Show, rotate or clean up the single access token';

    /** Поля, которые остались от схемы с отдельным токеном на каждый канал. */
    private const LEGACY = [
        'proxy' => ['access_token'],
        'security' => ['mcp_token', 'api_token'],
    ];

    public function handle(): int
    {
        try {
            app(SettingsInitializer::class)->ensure();
            $security = app(SecuritySettings::class);
        } catch (Throwable $exception) {
            $this->error('Не удалось прочитать настройки: '.$exception->getMessage());
            $this->line('Проверьте подключение к базе и выполните php artisan migrate.');

            return self::FAILURE;
        }

        $stored = trim((string) $security->access_token);
        $token = $stored !== '' ? $stored : trim((string) config('brain.tokens.all', ''));
        $source = $stored !== '' ? 'настройки' : 'BRAIN_TOKEN';

        if ($this->option('prune')) {
            if (! $this->pruneLegacy($token)) {
                return self::FAILURE;
            }
        }

        if ($this->option('generate')) {
            $token = 'nb-'.Str::random(40);
            $security->access_token = $token;
            $security->save();

            $this->info('Новый токен сохранён. Он действует для прокси, MCP и API.');
            $this->newLine();
            $this->line('  '.$token);
            $this->newLine();
            $this->comment('Обновите его в клиентах: Bearer для /mcp/knowledge и токен в адресе /v1.');
            $this->comment('Старое значение больше не действует.');

            return self::SUCCESS;
        }

        $this->table(['Источник', 'Токен'], [[$source, $this->mask($token)]]);

        if ($this->isShippedDefault($token)) {
            $this->warn('Это значение лежит в репозитории: снаружи по нему не пустят.');
            $this->line('Сгенерируйте своё — php artisan brain:token --generate.');
        }

        if ($this->legacyRowsExist()) {
            $this->newLine();
            $this->warn('В settings остались поля старых токенов. Удалить: php artisan brain:token --prune.');
        }

        return self::SUCCESS;
    }

    /**
     * Старые поля хранят зашифрованное значение, которое мы больше не читаем.
     * Если действующий токен — значение из репозитория, а старые поля есть,
     * удалять нельзя: в них может лежать единственная копия рабочего токена.
     */
    private function pruneLegacy(string $activeToken): bool
    {
        if ($this->legacyRowsExist() && $this->isShippedDefault($activeToken) && ! $this->option('force')) {
            $this->error('В settings остались поля старых токенов, а действующий — значение из репозитория.');
            $this->line('Сначала задайте настоящий токен: BRAIN_TOKEN в .env или php artisan brain:token --generate.');
            $this->line('Удалить старые поля вместе с их значением: --prune --force.');

            return false;
        }

        $deleted = 0;

        foreach (self::LEGACY as $group => $names) {
            $deleted += DB::table('settings')
                ->where('group', $group)
                ->whereIn('name', $names)
                ->delete();
        }

        $this->info($deleted > 0
            ? "Удалено строк старых токенов: {$deleted}."
            : 'Строк старых токенов нет.');

        return true;
    }

    private function legacyRowsExist(): bool
    {
        foreach (self::LEGACY as $group => $names) {
            if (DB::table('settings')->where('group', $group)->whereIn('name', $names)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function isShippedDefault(string $token): bool
    {
        return in_array($token, (array) config('brain.tokens.defaults', []), true);
    }

    private function mask(string $token): string
    {
        if ($token === '') {
            return 'не задан';
        }

        if (mb_strlen($token) <= 12) {
            return $token;
        }

        return mb_substr($token, 0, 6).'…'.mb_substr($token, -4);
    }
}
