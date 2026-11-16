<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Вход в кабинет по обычному web-гарду.
 *
 * Регистрации нет: людей заводит админ командой brain:user. Отключённый
 * пользователь войти не может — is_active проверяется прямо в попытке входа.
 */
class LoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('app.dashboard');
        }

        return view('app.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $authenticated = Auth::attempt(
            [
                'email' => mb_strtolower(trim($credentials['email'])),
                'password' => $credentials['password'],
                'is_active' => true,
            ],
            $request->boolean('remember'),
        );

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => 'Неверный email или пароль, либо пользователь отключён.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('app.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Вы вышли из кабинета.');
    }
}
