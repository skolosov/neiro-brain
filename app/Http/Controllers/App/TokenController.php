<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Auth\TokenIssuer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Токены пользователя в кабинете.
 *
 * Плейн-токен показывается один раз: он уходит во flash-сессию сразу после
 * выпуска, а в базе остаётся только хеш. Отзыв — удаление своей строки;
 * чужая не находится через связь user->apiTokens() и даёт 404.
 */
class TokenController extends Controller
{
    public function index(Request $request): View
    {
        return view('app.tokens', [
            'tokens' => $request->user()->apiTokens()->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request, TokenIssuer $issuer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(['read', 'write'])],
        ]);

        $result = $issuer->issue(
            $request->user(),
            $validated['name'],
            array_values($validated['abilities']),
        );

        return redirect()
            ->route('app.tokens.index')
            ->with('plain_token', $result['plain'])
            ->with('plain_token_name', $result['token']->name);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $model = $request->user()->apiTokens()->findOrFail($token);

        $model->delete();

        return back()->with('status', 'Токен «'.$model->name.'» отозван. Он больше не действует.');
    }
}
