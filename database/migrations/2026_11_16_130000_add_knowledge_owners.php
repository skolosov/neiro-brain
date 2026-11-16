<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * У каждой карточки появляется владелец.
 *
 * Порядок обязателен и одинаков для PostgreSQL и SQLite: сначала nullable-
 * колонка, потом бэкфилл существующих строк, и только затем NOT NULL. Старые
 * строки привязываются к первому админу MoonShine (пользователь `users`
 * создаётся по его email), а если админов нет — к `owner@local`.
 *
 * Глобальный unique по content_hash заменяется на unique(owner_id, content_hash):
 * одинаковый текст у двух владельцев — это две разные карточки, а не дубль.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 8)->default('ru');
            $table->boolean('is_active')->default(true);
        });

        Schema::table('moonshine_users', function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('knowledge_entries', function (Blueprint $table): void {
            $table->foreignId('owner_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();
        });

        $this->backfillOwner();

        Schema::table('knowledge_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('owner_id')->nullable(false)->change();
        });

        Schema::table('knowledge_entries', function (Blueprint $table): void {
            $table->dropUnique('knowledge_entries_content_hash_unique');
        });

        Schema::table('knowledge_entries', function (Blueprint $table): void {
            $table->unique(['owner_id', 'content_hash'], 'knowledge_entries_owner_content_hash_unique');
            $table->index(['owner_id', 'project'], 'knowledge_entries_owner_project_index');
            $table->index(['owner_id', 'status', 'kind'], 'knowledge_entries_owner_status_kind_index');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_entries', function (Blueprint $table): void {
            $table->dropUnique('knowledge_entries_owner_content_hash_unique');
            $table->dropIndex('knowledge_entries_owner_project_index');
            $table->dropIndex('knowledge_entries_owner_status_kind_index');
        });

        // Вернуть глобальную уникальность хеша можно только если после
        // разделения по владельцам в таблице не осталось одинаковых текстов.
        $hasDuplicates = DB::table('knowledge_entries')
            ->select('content_hash')
            ->groupBy('content_hash')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if (! $hasDuplicates) {
            Schema::table('knowledge_entries', function (Blueprint $table): void {
                $table->unique('content_hash');
            });
        }

        Schema::table('knowledge_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_id');
        });

        Schema::table('moonshine_users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['locale', 'is_active']);
        });
    }

    /**
     * Привязать существующие карточки к владельцу. Если строк нет, ничего не
     * создаём: на свежей базе владельца заводит сидер или первый запрос.
     */
    private function backfillOwner(): void
    {
        $orphans = DB::table('knowledge_entries')->whereNull('owner_id');

        if (! $orphans->exists()) {
            return;
        }

        $ownerId = $this->resolveOwnerId();

        $orphans->update(['owner_id' => $ownerId]);
    }

    private function resolveOwnerId(): int
    {
        $admin = DB::table('moonshine_users')->orderBy('id')->first(['id', 'email', 'name', 'password']);

        if ($admin !== null && is_string($admin->email) && $admin->email !== '') {
            $existing = DB::table('users')->where('email', $admin->email)->value('id');

            if ($existing !== null) {
                return (int) $existing;
            }

            // Пароль берём из админки: у человека должен быть один пароль на
            // кабинет и админку, иначе после миграции он не войдёт в кабинет.
            $password = is_string($admin->password ?? null) && $admin->password !== ''
                ? $admin->password
                : Hash::make(Str::random(40));

            return (int) DB::table('users')->insertGetId([
                'name' => (string) ($admin->name !== '' ? $admin->name : 'Владелец знаний'),
                'email' => $admin->email,
                'password' => $password,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $existing = DB::table('users')->where('email', 'owner@local')->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('users')->insertGetId([
            'name' => 'Владелец знаний',
            'email' => 'owner@local',
            'password' => Hash::make(Str::random(40)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
