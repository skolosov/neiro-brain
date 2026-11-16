<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

/**
 * Людей заводит админ командой: регистрации в кабинете нет.
 */
class UserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_a_user_with_password(): void
    {
        $this->artisan('brain:user', [
            '--email' => 'new@example.test',
            '--name' => 'Новый человек',
            '--password' => 'secret-pass',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'new@example.test')->firstOrFail();

        $this->assertSame('Новый человек', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('secret-pass', $user->password));
    }

    public function test_command_updates_an_existing_user(): void
    {
        $user = User::query()->create([
            'name' => 'Старое имя',
            'email' => 'old@example.test',
            'password' => 'old-pass',
        ]);

        $this->artisan('brain:user', [
            '--email' => 'old@example.test',
            '--name' => 'Новое имя',
            '--password' => 'new-pass',
        ])->assertSuccessful();

        $user->refresh();

        $this->assertSame('Новое имя', $user->name);
        $this->assertTrue(Hash::check('new-pass', $user->password));
        $this->assertSame(1, User::query()->where('email', 'old@example.test')->count());
    }

    public function test_command_lists_users(): void
    {
        $this->artisan('brain:user', [
            '--email' => 'listed@example.test',
            '--name' => 'В списке',
            '--password' => 'password',
        ])->assertSuccessful();

        $this->artisan('brain:user', ['--list' => true])
            ->expectsOutputToContain('listed@example.test')
            ->assertSuccessful();
    }

    public function test_command_disables_a_user(): void
    {
        $this->artisan('brain:user', [
            '--email' => 'off@example.test',
            '--name' => 'Отключаемый',
            '--password' => 'password',
        ])->assertSuccessful();

        $this->artisan('brain:user', ['--disable' => 'off@example.test'])->assertSuccessful();

        $this->assertFalse(User::query()->where('email', 'off@example.test')->firstOrFail()->is_active);
    }

    public function test_create_flag_refuses_to_overwrite_an_existing_user(): void
    {
        $this->artisan('brain:user', [
            '--email' => 'exists@example.test',
            '--name' => 'Есть',
            '--password' => 'password',
        ])->assertSuccessful();

        $this->artisan('brain:user', [
            '--email' => 'exists@example.test',
            '--name' => 'Ещё раз',
            '--password' => 'password',
            '--create' => true,
        ])->assertFailed();
    }

    public function test_admin_option_links_a_moonshine_user(): void
    {
        $this->artisan('brain:user', [
            '--email' => 'admin@example.test',
            '--name' => 'Админ',
            '--password' => 'password',
            '--admin' => true,
        ])->assertSuccessful();

        $user = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $admin = MoonshineUser::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame($user->getKey(), (int) $admin->user_id);
    }

    /**
     * У человека один пароль на кабинет и админку: иначе он входит в одну
     * дверь и не может войти в соседнюю, хотя пароль «верный».
     */
    public function test_password_is_the_same_for_cabinet_and_admin(): void
    {
        $this->artisan('brain:user', [
            '--email' => 'boss@example.test',
            '--name' => 'Босс',
            '--password' => 'first-pass',
            '--admin' => true,
        ])->assertSuccessful();

        $this->artisan('brain:user', [
            '--email' => 'boss@example.test',
            '--password' => 'second-pass',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'boss@example.test')->firstOrFail();
        $admin = MoonshineUser::query()->where('email', 'boss@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('second-pass', $user->password));
        $this->assertTrue(Hash::check('second-pass', $admin->password));
        $this->assertSame((int) $admin->user_id, (int) $user->getKey());
    }

    public function test_resetting_a_password_also_updates_an_linked_admin(): void
    {
        $this->artisan('brain:user', [
            '--email' => 'old@example.test',
            '--name' => 'Старый админ',
            '--password' => 'old-pass',
            '--admin' => true,
        ])->assertSuccessful();

        $this->artisan('brain:user', [
            '--email' => 'old@example.test',
            '--password' => 'new-pass',
            '--admin' => true,
        ])->assertSuccessful();

        $admin = MoonshineUser::query()->where('email', 'old@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('new-pass', $admin->password));
        $this->assertFalse(Hash::check('old-pass', $admin->password));
    }
}
