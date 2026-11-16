<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\OwnerResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use MoonShine\Laravel\Models\MoonshineUser;

/**
 * Люди, которые пользуются базой знаний.
 *
 * Регистрации нет: пользователя заводит админ командой. Токены он выпускает
 * сам в кабинете, поэтому команда паролем только задаёт вход.
 */
class BrainUserCommand extends Command
{
    protected $signature = 'brain:user
                            {--email= : Email пользователя}
                            {--name= : Имя}
                            {--password= : Пароль (обязателен при создании)}
                            {--admin : Сделать администратором MoonShine}
                            {--create : Только создать, не обновлять существующего}
                            {--list : Показать пользователей}
                            {--disable= : Отключить пользователя по id или email}';

    protected $description = 'Create, list or disable knowledge base users';

    public function handle(OwnerResolver $resolver): int
    {
        if ($this->option('list')) {
            return $this->listUsers();
        }

        $disable = trim((string) $this->option('disable'));

        if ($disable !== '') {
            return $this->disableUser($resolver, $disable);
        }

        return $this->upsertUser();
    }

    private function upsertUser(): int
    {
        $email = mb_strtolower(trim((string) $this->option('email')));

        if ($email === '') {
            $this->error('Укажите email: --email=user@example.com');

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name'));
        $password = (string) ($this->option('password') ?? '');
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && $this->option('create')) {
            $this->error('Пользователь «'.$email.'» уже существует. Уберите --create, чтобы обновить его.');

            return self::FAILURE;
        }

        if ($existing === null && ($name === '' || $password === '')) {
            $this->error('Для нового пользователя нужны --name и --password.');

            return self::FAILURE;
        }

        $user = $existing ?? new User;
        $user->email = $email;

        if ($name !== '') {
            $user->name = $name;
        }

        if ($password !== '') {
            // Каст password => hashed сам превратит значение в хеш.
            $user->password = $password;
        }

        $user->is_active = true;
        $user->save();

        if ($this->option('admin')) {
            $this->linkAdmin($user, $password);
        } elseif ($password !== '') {
            $this->syncAdminPassword($user, $password);
        }

        $this->info($existing === null
            ? 'Пользователь создан: '.$user->email
            : 'Пользователь обновлён: '.$user->email);

        if ($password !== '') {
            $this->line('Пароль задан. Токены для MCP и API выпускаются в кабинете: /app/tokens.');
        }

        return self::SUCCESS;
    }

    private function disableUser(OwnerResolver $resolver, string $handle): int
    {
        $user = $resolver->find($handle);

        if ($user === null) {
            $this->error('Пользователь «'.$handle.'» не найден. Укажите id или email.');

            return self::FAILURE;
        }

        $user->is_active = false;
        $user->save();

        $this->info('Пользователь отключён: '.$user->email);
        $this->line('Его токены остаются в базе, но перестают действовать, пока пользователь отключён.');

        return self::SUCCESS;
    }

    private function listUsers(): int
    {
        $users = User::query()
            ->withCount('apiTokens')
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->warn('Пользователей нет. Создайте первого: php artisan brain:user --email=... --name=... --password=...');

            return self::SUCCESS;
        }

        $adminIds = DB::table('moonshine_users')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        $rows = $users->map(fn (User $user): array => [
            $user->getKey(),
            $user->name,
            $user->email,
            $user->is_active ? 'да' : 'нет',
            in_array($user->getKey(), $adminIds, true) ? 'да' : '—',
            $user->api_tokens_count,
        ])->all();

        $this->table(['id', 'Имя', 'Email', 'Активен', 'Админ', 'Токенов'], $rows);

        return self::SUCCESS;
    }

    private function linkAdmin(User $user, string $password): void
    {
        $admin = MoonshineUser::query()->firstOrNew(['email' => $user->email]);
        $admin->name = $user->name;
        $admin->moonshine_user_role_id = $admin->moonshine_user_role_id ?: 1;

        // Пароль админки всегда повторяет пароль кабинета: два разных пароля
        // у одного человека — это гарантированная путаница при входе.
        $admin->password = $password !== '' ? Hash::make($password) : ($admin->password ?: Hash::make(Str::random(40)));

        $admin->save();

        DB::table('moonshine_users')
            ->where('id', $admin->getKey())
            ->update(['user_id' => $user->getKey()]);

        $this->line('Доступ в админку MoonShine: '.$user->email);
    }

    /**
     * Уже существующая админка должна принимать тот же пароль, что и кабинет,
     * даже если команду запускали без --admin.
     */
    private function syncAdminPassword(User $user, string $password): void
    {
        $updated = DB::table('moonshine_users')
            ->where('email', $user->email)
            ->update([
                'password' => Hash::make($password),
                'user_id' => $user->getKey(),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->line('Пароль админки MoonShine обновлён тем же значением.');
        }
    }
}
