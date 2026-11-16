<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Кабинет пользователя: профиль и свои токены.
 *
 * Всё берётся через $request->user(): чужой id в запросе ничего не открывает.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tokens = $user->apiTokens()->orderByDesc('id')->get();

        return view('app.dashboard', [
            'user' => $user,
            'tokens' => $tokens,
            'locales' => $this->locales(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', Rule::in(array_keys($this->locales()))],
        ]);

        $request->user()->forceFill([
            'name' => trim($validated['name']),
            'locale' => $validated['locale'],
        ])->save();

        return back()->with('status', 'Профиль сохранён.');
    }

    /**
     * @return array<string, string>
     */
    private function locales(): array
    {
        return [
            'ru' => 'Русский',
            'en' => 'English',
        ];
    }
}
