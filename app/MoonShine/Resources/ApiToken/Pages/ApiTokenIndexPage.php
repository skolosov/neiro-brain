<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiToken\Pages;

use App\Models\User;
use App\MoonShine\Resources\ApiToken\ApiTokenResource;
use App\Services\Auth\OAuthConnections;
use App\Support\PanelAccess;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Components\FlexibleRender;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ApiTokenResource>
 */
class ApiTokenIndexPage extends IndexPage
{
    protected bool $isLazy = false;

    protected function fields(): iterable
    {
        $fields = [ID::make()->sortable()];

        // Обычному человеку колонка «Пользователь» ничего не добавляет: в списке
        // только его строки. Администратору она нужна — он видит всех.
        if (PanelAccess::isAdmin()) {
            $fields[] = BelongsTo::make('Пользователь', 'user', formatted: static fn ($user): string => (string) $user->name)
                ->badge();
        }

        return [
            ...$fields,
            Text::make('Имя токена', 'name'),
            Text::make('Способности', 'abilities_text'),
            Date::make('Последнее использование', 'last_used_at')->format('d.m.Y H:i'),
            Date::make('Истекает', 'expires_at')->format('d.m.Y H:i'),
            Date::make('Выпущен', 'created_at')->format('d.m.Y H:i')->sortable(),
        ];
    }

    /**
     * Плейн-токен живёт только в flash-сессии следующего запроса: показываем его
     * над таблицей один раз и больше никогда — восстановить значение нельзя.
     *
     * Рядом — подключённые OAuth-приложения: у них отдельные таблицы Passport,
     * поэтому отдельный блок, а не строка в списке личных токенов.
     */
    protected function topLayer(): array
    {
        $hint = FlexibleRender::make(fn (): string => '<p class="nb-peek-text" style="margin:0 0 .6rem">'
            .'Токенов может быть сколько угодно: по одному на ноутбук, сервер или харнесс. '
            .'Плейн <code>nb-…</code> показывается один раз, в базе лежит только хэш; '
            .'отзыв — кнопка «Отозвать», он действует сразу.</p>');

        $layers = [$hint];

        $plain = session('plain_token');

        if (is_string($plain) && $plain !== '') {
            $layers[] = FlexibleRender::make(fn (): string => view('moonshine.brain.token-issued', [
                'plain' => $plain,
                'name' => (string) session('plain_token_name', ''),
            ])->render());
        }

        $layers[] = FlexibleRender::make(fn (): string => $this->connectedApps());

        return [...$layers, ...parent::topLayer()];
    }

    /**
     * Блок «Подключённые приложения»: имя клиента, когда выдан, кнопка отзыва.
     *
     * Обычный человек видит свои приложения, администратор — все (тогда в
     * таблице появляется колонка «Человек»).
     */
    private function connectedApps(): string
    {
        $showOwner = PanelAccess::isAdmin();
        $apps = app(OAuthConnections::class)->visible(PanelAccess::ownerId(), $showOwner);

        $ownerNames = $showOwner
            ? User::query()
                ->whereKey($apps->pluck('user_id')->filter()->unique()->all())
                ->pluck('email', 'id')
                ->all()
            : [];

        return view('moonshine.brain.connected-apps', [
            'apps' => $apps,
            'ownerNames' => $ownerNames,
            'showOwner' => $showOwner,
            'status' => session('oauth_status'),
        ])->render();
    }

    /**
     * Стандартное удаление строки и есть отзыв: токен хранится только хешем,
     * и после удаления он мгновенно перестаёт действовать.
     */
    protected function modifyDeleteButton(ActionButtonContract $button): ActionButtonContract
    {
        return $button->setLabel('Отозвать');
    }
}
