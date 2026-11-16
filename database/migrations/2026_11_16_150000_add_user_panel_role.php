<?php

declare(strict_types=1);

use App\Enums\PanelRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Роль обычного человека для панели MoonShine.
 *
 * В пакете есть только роль Admin (id 1), а после отказа от кабинета все входят
 * в панель — значит, нужна вторая роль. id задаётся явно, чтобы константа
 * `PanelRole::User` совпадала с базой на любой машине: созданная автоинкрементом
 * роль получила бы разный id в разных развёртываниях.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roles = DB::table('moonshine_user_roles');
        $id = PanelRole::User->value;

        if ($roles->where('id', $id)->exists()) {
            return;
        }

        DB::table('moonshine_user_roles')->insert([
            'id' => $id,
            'name' => PanelRole::User->label(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // На PostgreSQL явный id не сдвигает последовательность: без этого
        // следующая роль, созданная из панели, упала бы на дубле id.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('moonshine_user_roles', 'id'), "
                .'(SELECT MAX(id) FROM moonshine_user_roles))',
            );
        }
    }

    public function down(): void
    {
        DB::table('moonshine_user_roles')
            ->where('id', PanelRole::User->value)
            ->delete();
    }
};
