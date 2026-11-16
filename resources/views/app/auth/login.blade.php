@extends('app.layout')

@section('title', 'Вход')

@section('content')
    <div class="auth-card card">
        <div class="kicker">Neiro Brain</div>
        <h1>Вход в кабинет</h1>
        <p class="muted">
            Регистрации нет: пользователя заводит администратор.
            Если пароль забыт — попросите администратора задать новый.
        </p>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>

            <div class="field">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="remember" value="1">
                    Запомнить меня
                </label>
            </div>

            <button type="submit" class="btn">Войти</button>
        </form>

        <p class="muted" style="margin-top:16px">
            Токены для MCP и API выпускаются уже внутри кабинета.
        </p>
    </div>
@endsection
