<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Доступ к Neiro Brain — {{ $client->name }}</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #0f0c1d; color: #f5f2ff; line-height: 1.55; }
        .card { width: 100%; max-width: 460px; background: #17122b; border: 1px solid #332b52; border-radius: 16px; padding: 26px 26px 22px; }
        .kicker { color: #c4b5fd; letter-spacing: .14em; text-transform: uppercase; font-size: 11px; margin: 0 0 8px; }
        h1 { font-size: 21px; line-height: 1.25; margin: 0 0 6px; }
        h1 span { color: #a78bfa; }
        p { margin: 0 0 14px; font-size: 14px; color: #c9c3dd; }
        .who { margin: 18px 0; padding: 12px 14px; border: 1px solid #2e2650; border-radius: 12px; background: #1b1533; font-size: 13px; }
        .who dt { color: #8f86ab; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; margin: 0 0 2px; }
        .who dd { margin: 0 0 10px; color: #ede9fe; }
        .who dd:last-child { margin-bottom: 0; }
        ul.scopes { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
        ul.scopes li { display: flex; gap: 9px; align-items: flex-start; font-size: 14px; color: #c9c3dd; }
        ul.scopes li::before { content: "✓"; color: #a78bfa; font-weight: 700; }
        code { font-family: ui-monospace, SFMono-Regular, "Cascadia Mono", Consolas, monospace; font-size: 12px; overflow-wrap: anywhere; }
        .actions { display: flex; gap: 10px; margin-top: 22px; }
        .actions form { flex: 1; margin: 0; }
        button { width: 100%; padding: 11px 14px; border-radius: 10px; font-size: 14px; font-weight: 650; cursor: pointer; font-family: inherit; }
        button.allow { background: #7c3aed; color: #fff; border: 1px solid #7c3aed; }
        button.allow:hover { background: #6d28d9; }
        button.deny { background: transparent; color: #ddd6fe; border: 1px solid #4c3f78; }
        button.deny:hover { border-color: #6d5a9c; }
        .hint { margin: 16px 0 0; font-size: 12px; color: #8f86ab; }
    </style>
</head>
<body>
<main class="card">
    <p class="kicker">Neiro Brain</p>
    <h1>Подключить <span>{{ $client->name }}</span> к вашей базе знаний?</h1>
    <p>Приложение просит доступ к карточкам знаний. Пока вы не разрешите, оно ничего не увидит.</p>

    <div class="who">
        <dl>
            <dt>Вы вошли как</dt>
            <dd>{{ $user->email }}</dd>

            @php($redirectUri = request()->query('redirect_uri', $client->redirect_uris[0] ?? null))
            @if (is_string($redirectUri) && $redirectUri !== '')
                <dt>Куда вернёт после согласия</dt>
                <dd><code>{{ $redirectUri }}</code></dd>
            @endif
        </dl>
    </div>

    <p style="margin-bottom:8px">Приложение получит:</p>
    <ul class="scopes">
        @forelse ($scopes as $scope)
            <li>
                <span>
                    @if ($scope->id === 'mcp:use')
                        Доступ к вашей базе знаний: поиск, чтение и запись карточек.
                    @else
                        {{ $scope->description }}
                    @endif
                </span>
            </li>
        @empty
            <li><span>Доступ к вашей базе знаний.</span></li>
        @endforelse
    </ul>

    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="deny">Отказать</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="allow">Разрешить</button>
        </form>
    </div>

    <p class="hint">
        Доступ можно отозвать в панели: раздел «Токены», блок «Подключённые приложения».
        Пароль и личные токены <code>nb-…</code> приложение не получает.
    </p>
</main>
</body>
</html>
