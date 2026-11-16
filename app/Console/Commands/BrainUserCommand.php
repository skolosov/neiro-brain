<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PanelRole;
use App\Models\User;
use App\Services\Auth\PanelAccount;
use App\Support\OwnerResolver;
use Illuminate\Console\Command;

/**
 * Люди, которые пользуются базой знаний.
 *
 * Регистрации нет: человека заводит команда. Интерфейс один — панель MoonShine,
 * поэтому команда всегда создаёт (или обновляет) учётную запись панели и
 * привязывает её к человеку через `moonshine_users.user_id`. С `--admin` роль
 * администратора, без него — обычный пользователь; пароль один на обе записи.
 */
class BrainUserCommand extends Command
{
    protected $signature = 'brain:user
                            {--email= : Email пользователя}
                            {--name= : Имя}
                            {--password= : Пароль (обязателен при создании)}
                            {--admin : Сделать администратором панели}
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

        // Роль меняем только по флагу: сброс пароля без --admin не должен
        // незаметно отбирать админские права. Новой записи без флага достаётся
        // роль «Пользователь».
        $account = app(PanelAccount::class)->sync(
            $user,
            $password !== '' ? $password : null,
            $this->option('admin') ? PanelRole::Admin : null,
        );

        $this->info($existing === null
            ? 'Пользователь создан: '.$user->email
            : 'Пользователь обновлён: '.$user->email);

        $this->line('Вход в панель: /admin/login. Роль: '.PanelRole::fromId($account->moonshine_user_role_id)->label().'.');

        if ($password !== '') {
            $this->line('Пароль один для базы и панели. Токены выпускаются в панели, раздел «Токены».');
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
        $this->line('Вход в панель закрыт: учётная запись не привязана к активному человеку.');

        return self::SUCCESS;
    }

    private function listUsers(): int
    {
        $users = User::query()
            ->with(['moonshineUser.moonshineUserRole'])
            ->withCount('apiTokens')
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->warn('Пользователей нет. Создайте первого: php artisan brain:user --email=... --name=... --password=...');

            return self::SUCCESS;
        }

        $rows = $users->map(fn (User $user): array => [
            $user->getKey(),
            $user->name,
            $user->email,
            $user->is_active ? 'да' : 'нет',
            $user->moonshineUser?->moonshineUserRole?->name ?? '—',
            $user->api_tokens_count,
        ])->all();

        $this->table(['id', 'Имя', 'Email', 'Активен', 'Роль', 'Токенов'], $rows);

        return self::SUCCESS;
    }
}
