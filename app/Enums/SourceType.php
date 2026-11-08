<?php

declare(strict_types=1);

namespace App\Enums;

enum SourceType: string
{
    case Proxy = 'proxy';
    case Jsonl = 'jsonl';
    case ChatGpt = 'chatgpt';
    case Markdown = 'markdown';
    case Manual = 'manual';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Proxy => 'Прокси чатов',
            self::Jsonl => 'JSONL-транскрипты',
            self::ChatGpt => 'Экспорт ChatGPT',
            self::Markdown => 'Markdown-заметки',
            self::Manual => 'Вручную',
            self::Api => 'API',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function toString(): string
    {
        return $this->label();
    }
}
