<?php

declare(strict_types=1);

namespace App\Enums;

enum ConversationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Summarized = 'summarized';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ожидает обработки',
            self::Processing => 'Обрабатывается',
            self::Summarized => 'Обработано',
            self::Failed => 'Ошибка',
            self::Skipped => 'Пропущено',
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
