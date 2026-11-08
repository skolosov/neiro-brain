<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SyncSettings extends Settings
{
    public bool $enabled = true;

    /** Minutes of silence before a conversation is summarized. */
    public int $quiet_minutes = 2;

    public int $batch_size = 20;

    public bool $auto_approve = true;

    public bool $summarize_on_sync = true;

    public int $max_messages_per_conversation = 400;

    public int $search_log_retention_days = 90;

    public int $max_transcript_chars = 24000;

    public static function group(): string
    {
        return 'sync';
    }
}
