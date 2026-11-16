<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\KnowledgeTheme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KnowledgeThemeTest extends TestCase
{
    #[DataProvider('aliases')]
    public function test_it_normalizes_aliases_to_canonical_themes(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, KnowledgeTheme::normalize($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function aliases(): array
    {
        return [
            'русский поиск' => ['Поиск', 'search'],
            'английский поиск' => ['search', 'search'],
            'архитектура' => ['архитектура', 'architecture'],
            'админка' => ['админка', 'admin'],
            'деплой' => ['деплой', 'deploy'],
            'тесты' => ['тесты', 'tests'],
            'инфраструктура' => ['инфраструктура', 'infra'],
            'интерфейс' => ['интерфейс', 'ui'],
            'данные' => ['данные', 'data'],
            'процессы' => ['процессы', 'process'],
            'безопасность' => ['безопасность', 'security'],
            'llm' => ['llm', 'llm'],
            'каноническая тема' => ['tests', 'tests'],
            'лишние пробелы и регистр' => ['  Поиск  ', 'search'],
            'неизвестная тема' => ['лошади', 'other'],
            'пустая строка' => ['', null],
            'null' => [null, null],
        ];
    }
}
