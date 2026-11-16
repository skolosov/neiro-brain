<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auth\OAuthConnections;
use App\Support\PanelAccess;
use Illuminate\Http\RedirectResponse;
use Laravel\Passport\Passport;

/**
 * Отзыв доступа OAuth-приложения из панели.
 *
 * Маршрут вне MoonShine, но под её входом: чужой токен не находится (404), свой
 * удаляется. После отзыва следующий запрос клиента получает 401 — Passport
 * считает отсутствующий токен отозванным.
 */
class OAuthAppController extends Controller
{
    public function destroy(string $token, OAuthConnections $connections): RedirectResponse
    {
        $app = Passport::token()->newQuery()->with('client')->find($token);

        abort_if($app === null, 404);

        if (! PanelAccess::isAdmin() && (int) $app->user_id !== PanelAccess::ownerId()) {
            abort(404);
        }

        $name = (string) ($app->client?->name ?? 'Приложение');

        $connections->revoke($app);

        return back()->with('oauth_status', sprintf('Доступ «%s» отозван.', $name));
    }
}
