<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EntryStatus;
use App\Enums\KnowledgeKind;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Settings\SettingsInitializer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use MoonShine\Laravel\Models\MoonshineUser;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(SettingsInitializer::class)->ensure();
        $this->seedAdmin();
        $this->seedKnowledge();
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
            $writer->write($entry, EntryStatus::Approved, ['engine' => 'demo']);
        }

        $existing = KnowledgeEntry::query()->where('title', 'Короткие ответы без воды')->first();

        if ($existing !== null) {
            $existing->forceFill(['last_verified_at' => now()])->save();
        }
    }
}
