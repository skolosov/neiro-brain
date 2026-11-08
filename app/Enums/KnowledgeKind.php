<?php

declare(strict_types=1);

namespace App\Enums;

enum KnowledgeKind: string
{
    case Profile = 'profile';
    case Preference = 'preference';
    case Project = 'project';
    case Decision = 'decision';
    case Person = 'person';
    case Company = 'company';
    case Stack = 'stack';
    case Style = 'style';
    case Process = 'process';
    case Fact = 'fact';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Profile => 'Профиль',
            self::Preference => 'Предпочтение',
            self::Project => 'Проект',
            self::Decision => 'Решение',
            self::Person => 'Человек',
            self::Company => 'Компания',
            self::Stack => 'Стек',
            self::Style => 'Стиль работы',
            self::Process => 'Процесс',
            self::Fact => 'Факт',
            self::Other => 'Другое',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Profile => 'blue',
            self::Preference => 'purple',
            self::Project => 'green',
            self::Decision => 'orange',
            self::Person => 'pink',
            self::Company => 'cyan',
            self::Stack => 'yellow',
            self::Style => 'indigo',
            self::Process => 'teal',
            self::Fact => 'gray',
            self::Other => 'gray',
        };
    }

    public function toString(): string
    {
        return $this->label();
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
}
