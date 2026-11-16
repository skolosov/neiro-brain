<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\KnowledgeKind;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Settings\SettingsInitializer;
use App\Support\OwnerContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use MoonShine\Laravel\Models\MoonshineUser;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(SettingsInitializer::class)->ensure();

        $owner = $this->seedOwner();
        $this->seedAdmin($owner);

        // Сидер пишет демо-карточки тем же KnowledgeWriter, что MCP и API,
        // поэтому владельца нужно положить в контекст.
        app(OwnerContext::class)->set($owner);

        $this->seedKnowledge();
    }

    /**
     * Владелец знаний. Идемпотентно: повторный запуск находит пользователя по
     * email и не трогает его пароль.
     */
    private function seedOwner(): User
    {
        $email = (string) config('brain.demo.admin_email');

        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Neiro Brain Admin',
                'password' => Hash::make((string) config('brain.demo.admin_password')),
            ],
        );
    }

    private function seedAdmin(User $owner): void
    {
        $email = (string) config('brain.demo.admin_email');
        $password = (string) config('brain.demo.admin_password');

        $admin = MoonshineUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Neiro Brain Admin',
                'password' => Hash::make($password),
                'moonshine_user_role_id' => 1,
            ],
        );

        // Связь админки с владельцем знаний. Пишем через query builder, чтобы
        // не перехешировать пароль админа повторным save().
        DB::table('moonshine_users')
            ->where('id', $admin->getKey())
            ->update(['user_id' => $owner->getKey()]);

        $this->command?->info('Admin: '.$email.' / '.$password);
    }

    private function seedKnowledge(): void
    {
        $writer = app(KnowledgeWriter::class);

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
            $writer->write($entry, null, ['engine' => 'demo']);
        }

        $existing = KnowledgeEntry::query()->where('title', 'Короткие ответы без воды')->first();

        if ($existing !== null) {
            $existing->forceFill(['last_verified_at' => now()])->save();
        }
    }
}
