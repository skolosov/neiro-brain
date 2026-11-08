<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EntryStatus;
use App\Enums\KnowledgeKind;
use App\Enums\SourceType;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Models\SearchLog;
use App\Models\SyncRun;
use App\Services\Brain\ConversationIngestor;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Services\Brain\SourceRegistry;
use App\Services\SummaryService;
use App\Settings\SettingsInitializer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use MoonShine\Laravel\Models\MoonshineUser;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(SettingsInitializer::class)->ensure();
        $this->seedAdmin();
        $sources = $this->seedSources();
        $this->seedConversations($sources['proxy']);
        $this->seedManualKnowledge();
        $this->seedSearchLogs();
        $this->seedSyncRun();
    }

    private function seedAdmin(): void
    {
        $email = (string) config('brain.demo.admin_email');
        $password = (string) config('brain.demo.admin_password');

        MoonshineUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Neiro Brain Admin',
                'password' => Hash::make($password),
                'moonshine_user_role_id' => 1,
            ],
        );

        $this->command?->info('Admin: '.$email.' / '.$password);
    }

    /**
     * @return array{proxy: KnowledgeSource}
     */
    private function seedSources(): array
    {
        $registry = app(SourceRegistry::class);
        $proxy = $registry->proxy();
        $registry->manual();
        $registry->forSlug('api', 'Ingestion API', SourceType::Api);

        $jsonlPath = storage_path('app/demo/jsonl');
        File::ensureDirectoryExists($jsonlPath);
        File::put($jsonlPath.'/session-001.jsonl', implode("\n", [
            json_encode(['type' => 'user', 'message' => ['role' => 'user', 'content' => 'Для нового проекта я выбрал Laravel 13 и Postgres 16, потому что нужна надёжная очередь и полнотекстовый поиск.'], 'timestamp' => now()->subDays(2)->toIso8601String()], JSON_UNESCAPED_UNICODE),
            json_encode(['type' => 'assistant', 'message' => ['role' => 'assistant', 'content' => 'Хороший выбор для очередей и поиска.'], 'timestamp' => now()->subDays(2)->addMinute()->toIso8601String()], JSON_UNESCAPED_UNICODE),
            json_encode(['type' => 'user', 'message' => ['role' => 'user', 'content' => 'Ещё я не люблю микросервисы в маленькой команде: предпочитаю модульный монолит и чёткие границы модулей.'], 'timestamp' => now()->subDays(2)->addMinutes(2)->toIso8601String()], JSON_UNESCAPED_UNICODE),
        ])."\n");

        KnowledgeSource::query()->updateOrCreate(
            ['slug' => 'claude-code'],
            [
                'name' => 'Claude Code / Codex (JSONL)',
                'type' => 'jsonl',
                'enabled' => true,
                'config' => [
                    'path' => $jsonlPath,
                    'project' => 'neiro-brain',
                    'harness' => 'claude-code',
                ],
            ],
        );

        KnowledgeSource::query()->updateOrCreate(
            ['slug' => 'notes'],
            [
                'name' => 'Markdown-заметки',
                'type' => 'markdown',
                'enabled' => false,
                'config' => ['path' => storage_path('app/demo/notes'), 'project' => null],
            ],
        );

        return ['proxy' => $proxy];
    }

    private function seedConversations(KnowledgeSource $source): void
    {
        $ingestor = app(ConversationIngestor::class);
        $summaries = app(SummaryService::class);

        foreach ($this->conversations() as $conversation) {
            $result = $ingestor->ingest(
                source: $source,
                externalId: $conversation['external_id'],
                messages: $conversation['messages'],
                attributes: [
                    'title' => $conversation['title'],
                    'project' => $conversation['project'],
                    'harness' => $conversation['harness'],
                ],
            );

            $summaries->summarize($result['conversation'], force: true);
        }
    }

    /**
     * @return list<array{external_id: string, title: string, project: string|null, harness: string, messages: list<array{role: string, content: string}>}>
     */
    private function conversations(): array
    {
        return [
            [
                'external_id' => 'demo-001-stack',
                'title' => 'Стек и команда',
                'project' => 'neiro-brain',
                'harness' => 'claude-code',
                'messages' => [
                    ['role' => 'user', 'content' => 'Я работаю в компании «Северный код», мы делаем веб-сервисы для среднего бизнеса. В команде шесть разработчиков, включая меня.'],
                    ['role' => 'assistant', 'content' => 'Понял, зафиксировал контекст компании и размер команды.'],
                    ['role' => 'user', 'content' => 'Мой основной стек — Laravel 13, PostgreSQL 16, MoonShine для админок и Vue 3 на фронтенде. Локально всё крутится в OpenServer на Windows.'],
                    ['role' => 'assistant', 'content' => 'Записал стек: Laravel, Postgres, MoonShine, Vue и локальный OpenServer.'],
                    ['role' => 'user', 'content' => 'Я предпочитаю модульный монолит вместо микросервисов: так проще деплоить и удерживать границы домена в маленькой команде.'],
                    ['role' => 'assistant', 'content' => 'Принято: модульный монолит как архитектурное предпочтение.'],
                ],
            ],
            [
                'external_id' => 'demo-002-sklad',
                'title' => 'Проект Sklad',
                'project' => 'Sklad',
                'harness' => 'codex',
                'messages' => [
                    ['role' => 'user', 'content' => 'Проект Sklad — это учёт остатков для оптового склада. Клиент просил интеграцию с 1С и выгрузку накладных в XML.'],
                    ['role' => 'assistant', 'content' => 'Понял задачу интеграции с 1С и выгрузки накладных.'],
                    ['role' => 'user', 'content' => 'Для Sklad мы решили использовать Laravel 13, PostgreSQL и Redis для очередей. Деплой через Docker Compose на Ubuntu-сервер.'],
                    ['role' => 'assistant', 'content' => 'Зафиксировал стек и способ деплоя проекта Sklad.'],
                    ['role' => 'user', 'content' => 'В Sklad важна идемпотентность импорта из 1С: повторный импорт не должен создавать дубли накладных.'],
                ],
            ],
            [
                'external_id' => 'demo-003-crm',
                'title' => 'CRM для клиента',
                'project' => 'CRM',
                'harness' => 'cursor',
                'messages' => [
                    ['role' => 'user', 'content' => 'В проекте CRM мы делаем личный кабинет для клиентов и синхронизацию с Битрикс24.'],
                    ['role' => 'user', 'content' => 'Заказчик хочет, чтобы менеджеры видели историю сделок и могли ставить задачи прямо из карточки клиента.'],
                    ['role' => 'user', 'content' => 'Мы приняли решение не тащить тяжёлый фронтенд: используем Vue 3 через Inertia, чтобы не плодить отдельный API-слой.'],
                    ['role' => 'assistant', 'content' => 'Понял: CRM на Vue 3 + Inertia с интеграцией Битрикс24.'],
                    ['role' => 'user', 'content' => 'Для CRM я предпочитаю простые решения и минимум зависимостей: каждая новая библиотека должна быть оправдана.'],
                ],
            ],
            [
                'external_id' => 'demo-004-style',
                'title' => 'Стиль кода',
                'project' => 'neiro-brain',
                'harness' => 'claude-code',
                'messages' => [
                    ['role' => 'user', 'content' => 'Я пишу код с declare(strict_types=1) и стараюсь держать методы короткими.'],
                    ['role' => 'user', 'content' => 'В Laravel я предпочитаю экшены и DTO вместо толстых контроллеров и сервис-локаторов.'],
                    ['role' => 'user', 'content' => 'Тесты пишу на Pest, обязательно покрываю граничные случаи и ошибки валидации.'],
                    ['role' => 'user', 'content' => 'Я не люблю фасады в доменном слое и магию Eloquent за пределами инфраструктуры.'],
                    ['role' => 'assistant', 'content' => 'Записал предпочтения по стилю кода и тестированию.'],
                ],
            ],
            [
                'external_id' => 'demo-005-brain',
                'title' => 'Решение по Neiro Brain',
                'project' => 'neiro-brain',
                'harness' => 'claude-code',
                'messages' => [
                    ['role' => 'user', 'content' => 'Мы решили строить базу знаний для LLM на Laravel с PostgreSQL и BM25, без векторной базы на первом этапе.'],
                    ['role' => 'user', 'content' => 'Прокси должен быть полностью прозрачным: харнесс меняет только base URL, а память подмешивается в системный промпт на стороне сервера.'],
                    ['role' => 'user', 'content' => 'Для админки используем MoonShine, для конфигураций и секретов — spatie/laravel-settings с шифрованием.'],
                    ['role' => 'assistant', 'content' => 'Зафиксировал архитектурные решения по neiro-brain.'],
                    ['role' => 'user', 'content' => 'Синхронизация и суммаризация должны идти по крону каждые пятнадцать минут.'],
                ],
            ],
            [
                'external_id' => 'demo-006-company',
                'title' => 'Компания и клиенты',
                'project' => null,
                'harness' => 'chatgpt-export',
                'messages' => [
                    ['role' => 'user', 'content' => 'Наша компания специализируется на e-commerce и внутренних системах для клиентов из России.'],
                    ['role' => 'user', 'content' => 'Мы работаем по фиксированному скоупу, но оставляем буфер на изменения. Для клиента важнее предсказуемость, чем самая низкая цена.'],
                    ['role' => 'user', 'content' => 'Я не люблю созвоны без повестки и предпочитаю письменные брифы с чёткими критериями приёмки.'],
                    ['role' => 'assistant', 'content' => 'Понял подход компании к проектам и коммуникации.'],
                ],
            ],
            [
                'external_id' => 'demo-007-infra',
                'title' => 'Инфраструктура и деплой',
                'project' => 'Sklad',
                'harness' => 'codex',
                'messages' => [
                    ['role' => 'user', 'content' => 'Продовые серверы у нас на Ubuntu, перед nginx стоит Cloudflare.'],
                    ['role' => 'user', 'content' => 'Для деплоя использую GitHub Actions и Docker Compose, миграции запускаются отдельным шагом с бэкапом базы.'],
                    ['role' => 'user', 'content' => 'Я предпочитаю blue-green деплой для критичных сервисов, чтобы откат занимал секунды, а не минуты.'],
                ],
            ],
            [
                'external_id' => 'demo-008-personal',
                'title' => 'Личные предпочтения',
                'project' => null,
                'harness' => 'chatgpt-export',
                'messages' => [
                    ['role' => 'user', 'content' => 'Меня зовут Алексей, я живу в Москве и работаю в часовом поясе Europe/Moscow.'],
                    ['role' => 'user', 'content' => 'Я предпочитаю короткие ответы без воды: сначала вывод, потом детали.'],
                    ['role' => 'user', 'content' => 'Документацию читаю на русском, но технические термины оставляю на английском.'],
                    ['role' => 'user', 'content' => 'Мне нравится, когда код сопровождается понятным README и примерами запуска.'],
                ],
            ],
            [
                'external_id' => 'demo-009-secret',
                'title' => 'Проверка маскировки секретов',
                'project' => 'neiro-brain',
                'harness' => 'claude-code',
                'messages' => [
                    ['role' => 'user', 'content' => 'Для теста маскировки вот ключ: sk-proj-abcdefghijklmnopqrstuvwxyz123456, он не должен попасть в базу знаний.'],
                    ['role' => 'user', 'content' => 'Ещё один секрет: DATABASE_PASSWORD=super-secret-password. Убедись, что он будет вырезан.'],
                    ['role' => 'assistant', 'content' => 'Понял, проверяю маскировку секретов.'],
                ],
            ],
        ];
    }

    private function seedManualKnowledge(): void
    {
        $writer = app(KnowledgeWriter::class);
        $registry = app(SourceRegistry::class);

        $entries = [
            new ExtractedEntry(
                kind: KnowledgeKind::Preference,
                title: 'Короткие ответы без воды',
                content: 'Пользователь предпочитает короткие ответы: сначала вывод, затем детали. Не любит вступления и повторы.',
                project: null,
                tags: ['communication', 'preferences'],
                importance: 5,
                confidence: 1.0,
            ),
            new ExtractedEntry(
                kind: KnowledgeKind::Profile,
                title: 'Имя и часовой пояс',
                content: 'Пользователя зовут Алексей, он живёт в Москве и работает в часовом поясе Europe/Moscow.',
                project: null,
                tags: ['profile'],
                importance: 5,
                confidence: 1.0,
            ),
            new ExtractedEntry(
                kind: KnowledgeKind::Stack,
                title: 'Основной стек',
                content: 'Основной стек: Laravel 13, PostgreSQL 16, MoonShine, Vue 3, Redis, Docker. Локальная среда — OpenServer на Windows.',
                project: 'neiro-brain',
                tags: ['laravel', 'php', 'postgres', 'docker'],
                importance: 4,
                confidence: 0.95,
            ),
            new ExtractedEntry(
                kind: KnowledgeKind::Decision,
                title: 'BM25 вместо векторной базы',
                content: 'Для Neiro Brain принято решение начать с BM25 и PostgreSQL без векторной базы, чтобы сохранить прозрачность и простоту.',
                project: 'neiro-brain',
                tags: ['bm25', 'architecture'],
                importance: 4,
                confidence: 0.9,
            ),
        ];

        foreach ($entries as $entry) {
            $writer->write($entry, source: $registry->manual(), status: EntryStatus::Approved, metadata: ['engine' => 'demo']);
        }

        $existing = KnowledgeEntry::query()->where('title', 'Короткие ответы без воды')->first();

        if ($existing !== null) {
            $existing->forceFill(['last_verified_at' => now()])->save();
        }
    }

    private function seedSearchLogs(): void
    {
        $queries = [
            ['Какой стек у пользователя?', 'mcp', 3],
            ['Что мы решили по проекту Sklad?', 'proxy', 2],
            ['Стиль кода и тесты', 'mcp', 4],
            ['Какой подход к архитектуре предпочитает Алексей?', 'admin', 5],
        ];

        foreach ($queries as [$query, $source, $count]) {
            SearchLog::query()->create([
                'query' => $query,
                'source' => $source,
                'results_count' => $count,
                'latency_ms' => random_int(4, 40),
                'created_at' => now()->subMinutes(random_int(5, 600)),
            ]);
        }
    }

    private function seedSyncRun(): void
    {
        SyncRun::query()->create([
            'command' => 'brain:sync',
            'status' => 'completed',
            'stats' => [
                'summarized' => 9,
                'entries_created' => KnowledgeEntry::query()->count(),
                'entries_updated' => 0,
                'reindexed' => KnowledgeEntry::query()->count(),
            ],
            'started_at' => now()->subMinutes(12),
            'finished_at' => now()->subMinutes(11),
        ]);
    }
}
