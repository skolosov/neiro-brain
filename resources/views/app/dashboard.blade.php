@extends('app.layout')

@section('title', 'Кабинет')

@section('content')
    <div class="kicker">Личный кабинет</div>
    <h1>Здравствуйте, {{ $user->name }}</h1>
    <p class="lead">
        Здесь ваш профиль и доступ к базе знаний: личные токены для MCP и HTTP API.
        Карточки, которые пишет ваш агент, видите только вы.
    </p>

    <div class="grid">
        <section class="card tight">
            <h2>Профиль</h2>
            <form method="POST" action="{{ route('app.profile.update') }}">
                @csrf
                @method('PATCH')

                <div class="field">
                    <label for="name">Имя</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255">
                </div>

                <div class="field">
                    <label for="locale">Язык ответов</label>
                    <select id="locale" name="locale">
                        @foreach ($locales as $value => $label)
                            <option value="{{ $value }}" @selected(old('locale', $user->locale ?? 'ru') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn">Сохранить профиль</button>
            </form>
        </section>

        <section class="card tight">
            <h2>Доступ к базе</h2>
            <p class="muted" style="margin-bottom:10px">Email входа: {{ $user->email }}</p>
            <p style="margin-bottom:6px"><strong style="color:#ede9fe">{{ $tokens->count() }}</strong> токен(ов) выпущено</p>
            <p class="muted" style="margin-bottom:14px">
                @if ($tokens->whereNotNull('last_used_at')->isNotEmpty())
                    Последнее использование:
                    {{ $tokens->whereNotNull('last_used_at')->sortByDesc('last_used_at')->first()->last_used_at->format('d.m.Y H:i') }}
                @else
                    Ни один токен ещё не использовался.
                @endif
            </p>
            <div class="actions">
                <a class="btn secondary" href="{{ route('app.tokens.index') }}">Управлять токенами</a>
            </div>
        </section>
    </div>

    @include('app.partials.tokens')
@endsection
