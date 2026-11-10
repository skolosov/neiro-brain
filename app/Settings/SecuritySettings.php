<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

class SecuritySettings extends Settings
{
    public bool $mask_secrets = true;

    public bool $mask_emails = false;

    public string $redaction = '[СКРЫТО]';

    /**
     * Единственный токен доступа: прокси, MCP и API проверяют его.
     * Пустая строка означает «взять brain.tokens.all» — так установка
     * подхватывает BRAIN_TOKEN, не требуя записи в базу.
     */
    #[ShouldBeEncrypted]
    public string $access_token = '';

    /** @var list<string> */
    public array $custom_patterns = [];

    public static function group(): string
    {
        return 'security';
    }
}
