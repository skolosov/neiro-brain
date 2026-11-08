<?php

declare(strict_types=1);

namespace App\Enums;

enum EntryStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Approved => 'Подтверждено',
            self::Archived => 'В архиве',
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
