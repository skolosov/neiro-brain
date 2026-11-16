<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Канонические темы карточек знаний.
 *
 * Тема — одно слово латиницей в нижнем регистре. Список один на весь проект:
 * так поиск по теме и группировка не расползаются на «Поиск», «поиск» и
 * «search». Старые карточки и `TopicMap` знают свои темы; нормализация
 * применяется только к новым записям.
 */
enum KnowledgeTheme: string
{
    case Search = 'search';
    case Architecture = 'architecture';
    case Admin = 'admin';
    case Deploy = 'deploy';
    case Tests = 'tests';
    case Infra = 'infra';
    case Ui = 'ui';
    case Data = 'data';
    case Process = 'process';
    case Security = 'security';
    case Llm = 'llm';
    case Other = 'other';

    /**
     * Русские и английские написания, которые модель и человек пишут чаще всего.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'поиск' => self::Search->value,
        'поиска' => self::Search->value,
        'поиском' => self::Search->value,
        'search' => self::Search->value,
        'архитектура' => self::Architecture->value,
        'архитектуры' => self::Architecture->value,
        'архитектуре' => self::Architecture->value,
        'architecture' => self::Architecture->value,
        'арх' => self::Architecture->value,
        'админка' => self::Admin->value,
        'админки' => self::Admin->value,
        'админке' => self::Admin->value,
        'админ' => self::Admin->value,
        'admin' => self::Admin->value,
        'деплой' => self::Deploy->value,
        'деплоя' => self::Deploy->value,
        'деплое' => self::Deploy->value,
        'deploy' => self::Deploy->value,
        'deployment' => self::Deploy->value,
        'тесты' => self::Tests->value,
        'тестов' => self::Tests->value,
        'тестам' => self::Tests->value,
        'тест' => self::Tests->value,
        'тестирование' => self::Tests->value,
        'tests' => self::Tests->value,
        'test' => self::Tests->value,
        'инфраструктура' => self::Infra->value,
        'инфраструктуры' => self::Infra->value,
        'инфра' => self::Infra->value,
        'infra' => self::Infra->value,
        'infrastructure' => self::Infra->value,
        'интерфейс' => self::Ui->value,
        'интерфейса' => self::Ui->value,
        'интерфейсе' => self::Ui->value,
        'ui' => self::Ui->value,
        'interface' => self::Ui->value,
        'frontend' => self::Ui->value,
        'фронтенд' => self::Ui->value,
        'данные' => self::Data->value,
        'данных' => self::Data->value,
        'данным' => self::Data->value,
        'data' => self::Data->value,
        'database' => self::Data->value,
        'бд' => self::Data->value,
        'процессы' => self::Process->value,
        'процессов' => self::Process->value,
        'процесс' => self::Process->value,
        'process' => self::Process->value,
        'processes' => self::Process->value,
        'безопасность' => self::Security->value,
        'безопасности' => self::Security->value,
        'security' => self::Security->value,
        'llm' => self::Llm->value,
        'other' => self::Other->value,
        'другое' => self::Other->value,
    ];

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $theme): string => $theme->value, self::cases());
    }

    /**
     * Привести свободный текст темы к каноническому виду.
     *
     * Пустое значение остаётся пустым, незнакомое сводится к `other`: карточка
     * без темы теряет группировку, а карточка с произвольной темой — тем более.
     */
    public static function normalize(?string $theme): ?string
    {
        $theme = $theme !== null ? trim($theme) : '';

        if ($theme === '') {
            return null;
        }

        $key = mb_strtolower(preg_replace('/\s+/u', ' ', $theme) ?? $theme);
        $key = str_replace('ё', 'е', $key);

        if (self::tryFrom($key) !== null) {
            return $key;
        }

        return self::ALIASES[$key] ?? self::Other->value;
    }
}
