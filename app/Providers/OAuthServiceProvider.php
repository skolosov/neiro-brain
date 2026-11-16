<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\PanelPersonGuard;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;

/**
 * OAuth для MCP: область доступа, страница согласия и личность согласия.
 *
 * Клиент с поддержкой OAuth приходит без токена: получает 401, читает
 * метаданные, регистрируется и открывает браузер на /oauth/authorize. Согласие
 * выдаёт человек, вошедший в панель, поэтому Passport смотрит на сессию панели
 * (`moonshine`) через гард `panel` — он возвращает человека `users`, а не
 * учётку панели (см. PanelPersonGuard).
 *
 * Область одна: MCP умеет и читать, и писать, разделять права нечем. Личные
 * токены `nb-…` живут отдельно и продолжают работать.
 */
class OAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving('auth', static function (AuthManager $auth): void {
            $auth->extend('panel-person', static fn (): PanelPersonGuard => new PanelPersonGuard);
        });
    }

    public function boot(): void
    {
        Passport::tokensCan([
            Registrar::OAUTH_SCOPE => 'Доступ к вашей базе знаний',
        ]);

        // Своя страница вместо английской из laravel/mcp: имя клиента, одно
        // право по-русски и две кнопки. CSRF остаётся штатным.
        Passport::authorizationView('oauth.authorize');
    }
}
