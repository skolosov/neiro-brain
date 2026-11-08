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

    #[ShouldBeEncrypted]
    public string $mcp_token = 'dev-mcp-token';

    #[ShouldBeEncrypted]
    public string $api_token = 'dev-api-token';

    /** @var list<string> */
    public array $custom_patterns = [];

    public static function group(): string
    {
        return 'security';
    }
}
