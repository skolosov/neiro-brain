<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PanelRole;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use App\Support\OwnerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Http\Middleware\ChangeLocale;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

/**
 * Панель говорит по-русски: стандартные подписи MoonShine приходят из
 * lang/vendor/moonshine/ru, а английский оригинал остаётся только источником
 * ключей. Локаль приложения (config/app.php) при этом не меняется.
 */
class PanelLocaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * (а) В русском переводе ровно те же ключи, что в английском оригинале:
     * ни одного пропущенного или лишнего. Сравниваем все четыре файла — набор
     * ключей важнее самих строк, потому что пропуск даёт английскую подпись.
     */
    public function test_ru_translations_have_the_same_keys_as_en(): void
    {
        foreach (['ui', 'auth', 'pagination', 'validation'] as $file) {
            $en = $this->translationKeys('en', $file);
            $ru = $this->translationKeys('ru', $file);

            sort($en);
            sort($ru);

            $this->assertSame($en, $ru, "Набор ключей {$file}.php в ru и en разошёлся.");
            $this->assertNotSame([], $ru, "Файл {$file}.php в ru пуст.");
        }
    }

    /** (б) Внутри панели локаль приложения — ru, даже если в сессии ничего нет. */
    public function test_panel_uses_ru_locale_by_default(): void
    {
        $person = $this->user('locale@panel.test');
        $account = $this->panelAccount($person);

        $response = $this->actingAs($account, 'moonshine')
            ->get($this->tokenIndexUrl())
            ->assertOk();

        $this->assertSame('ru', app()->getLocale());
        $this->assertSame('ru', moonshineConfig()->getLocale());
        $this->assertSame(['ru' => 'Русский'], moonshineConfig()->getLocales());

        // Сессии ещё нет: локаль взялась из config/moonshine.php, а не из
        // переключателя. Значит, дефолт панели действительно русский.
        $response->assertSessionMissing(ChangeLocale::KEY);

        // Переключатель локали (ChangeLocale) по-прежнему работает: явный выбор
        // ru сохраняется в сессию и не ломает ни локаль, ни подписи.
        $this->actingAs($account, 'moonshine')
            ->get($this->tokenIndexUrl().'?'.ChangeLocale::KEY.'=ru')
            ->assertOk()
            ->assertSee('Создать')
            ->assertSessionHas(ChangeLocale::KEY, 'ru');

        $this->assertSame('ru', app()->getLocale());
    }

    /** (в) Кнопка создания в списке токенов — «Создать», а не «Create». */
    public function test_token_list_has_russian_create_button(): void
    {
        $person = $this->user('locale-token@panel.test');

        $this->actingAs($this->panelAccount($person), 'moonshine')
            ->get($this->tokenIndexUrl())
            ->assertOk()
            ->assertSee('Создать')
            ->assertDontSee('>Create<')
            ->assertDontSee('Create');
    }

    /** (г) Пагинация списка — «Назад» / «Вперёд», а не Previous / Next. */
    public function test_token_list_pagination_is_russian(): void
    {
        $person = $this->user('locale-page@panel.test');

        // Простая пагинация: 25 строк на страницу, значит 26 токенов дают «Вперёд».
        foreach (range(1, 26) as $number) {
            app(TokenIssuer::class)->issue($person, "Токен {$number}", ['read']);
        }

        app(OwnerContext::class)->forget();

        $this->actingAs($this->panelAccount($person), 'moonshine')
            ->get($this->tokenIndexUrl())
            ->assertOk()
            ->assertSee('&laquo; Назад', false)
            ->assertSee('Вперёд &raquo;', false)
            ->assertDontSee('Previous')
            ->assertDontSee('Next &raquo;', false);
    }

    /** (д) Уведомление после сохранения — «Сохранено», а не «Saved». */
    public function test_saved_notification_is_russian(): void
    {
        $person = $this->user('locale-saved@panel.test');
        $account = $this->panelAccount($person);

        $this->actingAs($account, 'moonshine')
            ->post(route('moonshine.crud.store', ['resourceUri' => 'api-token-resource']), [
                'name' => 'Новый токен',
                'abilities' => ['read'],
            ])
            ->assertRedirect();

        $this->actingAs($account, 'moonshine')
            ->get($this->tokenIndexUrl())
            ->assertOk()
            ->assertSee('Сохранено')
            ->assertDontSee('Saved');
    }

    /** @return list<string> */
    private function translationKeys(string $locale, string $file): array
    {
        $path = lang_path("vendor/moonshine/{$locale}/{$file}.php");

        $this->assertFileExists($path);

        return $this->flatten(require $path);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $keys = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $keys = [...$keys, ...$this->flatten($value, $path)];

                continue;
            }

            $keys[] = $path;
        }

        return $keys;
    }

    private function tokenIndexUrl(): string
    {
        return route('moonshine.resource.page', [
            'resourceUri' => 'api-token-resource',
            'pageUri' => 'api-token-index-page',
        ]);
    }

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

    /**
     * Вход в панель, привязанный к человеку: только у такого входа есть кнопка
     * создания собственного токена.
     */
    private function panelAccount(User $user): MoonshineUser
    {
        $account = MoonshineUser::query()->create([
            'name' => $user->name,
            'email' => $user->email,
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => PanelRole::User->value,
        ]);

        $account->forceFill(['user_id' => $user->getKey()])->save();

        return $account;
    }
}
