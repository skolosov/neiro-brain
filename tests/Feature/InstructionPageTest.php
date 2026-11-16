<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Brain\SkillText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

/**
 * Страница «Инструкция»: админ правит протокол, который сервер отдаёт
 * MCP-ресурсом `knowledge://skill/brain`. Файл остаётся значением по умолчанию,
 * а пустой текст и кнопка сброса возвращают именно его.
 */
class InstructionPageTest extends TestCase
{
    use RefreshDatabase;

    private const MCP_INITIALIZE = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/read',
        'params' => ['uri' => 'knowledge://skill/brain'],
    ];

    private function user(string $email): User
    {
        return User::query()->create([
            'name' => 'Человек',
            'email' => $email,
            'password' => 'password',
            'locale' => 'ru',
            'is_active' => true,
        ]);
    }

    private function panelAccount(User $user): MoonshineUser
    {
        $account = MoonshineUser::query()->create([
            'name' => $user->name,
            'email' => $user->email,
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => 2,
        ]);

        $account->forceFill(['user_id' => $user->getKey()])->save();

        return $account;
    }

    private function admin(): MoonshineUser
    {
        return MoonshineUser::query()->firstOrCreate(
            ['email' => 'admin@panel.test'],
            [
                'name' => 'Администратор',
                'password' => bcrypt('password'),
                'moonshine_user_role_id' => 1,
            ],
        );
    }

    private function pageUrl(): string
    {
        return route('moonshine.page', ['pageUri' => 'instruction-page']);
    }

    /**
     * Прочитать протокол так же, как это делает харнесс: MCP-ресурсом с токеном.
     */
    private function skillResourceText(): string
    {
        $issued = $this->issueToken($this->owner(), ['read', 'write']);

        $response = $this->postJson(
            '/mcp/knowledge',
            self::MCP_INITIALIZE,
            $this->mcpHeaders($issued['plain']),
        )->assertSuccessful();

        $text = $response->json('result.contents.0.text');

        $this->assertIsString($text);

        return $text;
    }

    /** (а) Админ открывает страницу и видит текст протокола. */
    public function test_admin_opens_the_page_and_sees_the_protocol(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'moonshine')
            ->get(route('moonshine.index'))
            ->assertOk()
            ->assertSee('Инструкция');

        $this->actingAs($admin, 'moonshine')
            ->get($this->pageUrl())
            ->assertOk()
            ->assertSee('Инструкция для агента')
            ->assertSee('Порядок работы')
            ->assertSee('remember_many')
            ->assertSee('Символов')
            ->assertSee('knowledge://skill/brain');
    }

    /** (б) Обычный человек не видит пункт меню и получает 403 по адресу. */
    public function test_regular_user_cannot_open_the_page_or_see_the_menu_item(): void
    {
        $account = $this->panelAccount($this->user('regular@panel.test'));

        $this->actingAs($account, 'moonshine')
            ->get(route('moonshine.index'))
            ->assertOk()
            ->assertDontSee('Инструкция');

        $this->actingAs($account, 'moonshine')
            ->get($this->pageUrl())
            ->assertForbidden();

        // Сохранение и сброс — тоже только администратору.
        $this->actingAs($account, 'moonshine')
            ->post(route('brain.admin.instruction'), ['skill_text' => 'Чужой протокол'])
            ->assertForbidden();

        $this->actingAs($account, 'moonshine')
            ->post(route('brain.admin.instruction.reset'))
            ->assertForbidden();
    }

    /** (в) Сохранённый текст меняет ответ MCP-ресурса. */
    public function test_saved_text_changes_the_mcp_resource(): void
    {
        $this->actingAs($this->admin(), 'moonshine')
            ->post(route('brain.admin.instruction'), [
                'skill_text' => '# Мой протокол

Сохраняй только решения.',
            ])
            ->assertRedirect()
            ->assertSessionHas('brain_status');

        $this->assertSame(
            "# Мой протокол\n\nСохраняй только решения.",
            $this->skillResourceText(),
        );
    }

    /** (г) Сброс возвращает текст файла-эталона. */
    public function test_reset_returns_the_file_text(): void
    {
        $this->actingAs($this->admin(), 'moonshine')
            ->post(route('brain.admin.instruction'), ['skill_text' => 'Временный протокол'])
            ->assertRedirect();

        $this->assertSame('Временный протокол', $this->skillResourceText());

        $this->actingAs($this->admin(), 'moonshine')
            ->post(route('brain.admin.instruction.reset'))
            ->assertRedirect()
            ->assertSessionHas('brain_status');

        $expected = file_get_contents(resource_path(SkillText::DEFAULT_PATH));

        $this->assertIsString($expected);
        $this->assertSame($expected, $this->skillResourceText());
    }

    /** (д) Пустой текст при сохранении — это тот же сброс к файлу. */
    public function test_empty_text_is_treated_as_reset(): void
    {
        $this->actingAs($this->admin(), 'moonshine')
            ->post(route('brain.admin.instruction'), ['skill_text' => 'Временный протокол'])
            ->assertRedirect();

        $this->actingAs($this->admin(), 'moonshine')
            ->post(route('brain.admin.instruction'), ['skill_text' => "  \n  "])
            ->assertRedirect();

        $expected = file_get_contents(resource_path(SkillText::DEFAULT_PATH));

        $this->assertIsString($expected);
        $this->assertSame($expected, $this->skillResourceText());
    }
}
