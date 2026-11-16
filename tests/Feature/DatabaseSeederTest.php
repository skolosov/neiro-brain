<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

/**
 * Демо-сидер: владелец с паролем для локального входа и связка с админкой,
 * повторный запуск не создаёт дублей.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_links_the_owner_to_the_admin(): void
    {
        $this->seed();
        $this->seed();

        $email = (string) config('brain.demo.admin_email');

        $this->assertSame(1, User::query()->where('email', $email)->count());
        $this->assertSame(1, MoonshineUser::query()->where('email', $email)->count());

        $owner = User::query()->where('email', $email)->firstOrFail();
        $admin = MoonshineUser::query()->where('email', $email)->firstOrFail();

        $this->assertTrue($owner->is_active);
        $this->assertTrue(Hash::check((string) config('brain.demo.admin_password'), $owner->password));
        $this->assertSame($owner->getKey(), (int) $admin->user_id);
    }
}
