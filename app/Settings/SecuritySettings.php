<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SecuritySettings extends Settings
{
    public bool $mask_secrets = true;

    public bool $mask_emails = false;

    public string $redaction = '[СКРЫТО]';

    /** @var list<string> */
    public array $custom_patterns = [];

    public static function group(): string
    {
        return 'security';
    }
}
