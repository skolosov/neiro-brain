<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Кабинет') — Neiro Brain</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; background: #0f0c1d; color: #f5f2ff; line-height: 1.55; }
        a { color: #c4b5fd; }
        .top { border-bottom: 1px solid #2a2342; background: #130f24; }
        .top-inner { max-width: 980px; margin: 0 auto; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .brand { color: #f5f2ff; text-decoration: none; font-weight: 750; letter-spacing: .02em; }
        .brand span { color: #a78bfa; }
        nav { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        nav a { text-decoration: none; color: #c9c3dd; font-size: 14px; }
        nav a:hover { color: #ede9fe; }
        nav form { display: inline; margin: 0; }
        .link-button { background: transparent; border: 0; color: #c9c3dd; font: inherit; font-size: 14px; cursor: pointer; padding: 0; }
        .link-button:hover { color: #ede9fe; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 36px 24px 64px; }
        .kicker { color: #c4b5fd; letter-spacing: .14em; text-transform: uppercase; font-size: 12px; }
        h1 { font-size: clamp(28px, 4vw, 42px); line-height: 1.1; margin: 8px 0 10px; }
        h2 { font-size: 19px; margin: 0 0 12px; color: #ede9fe; }
        p { color: #c9c3dd; margin: 0 0 12px; }
        .lead { max-width: 720px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin: 24px 0; }
        .card { border: 1px solid #332b52; background: #17122b; border-radius: 16px; padding: 20px; margin: 16px 0; }
        .card.tight { margin: 0; }
        .muted { color: #8f86ab; font-size: 13px; }
        code, .token-plain { background: #241c40; padding: 2px 7px; border-radius: 6px; color: #ddd6fe; }
        .token-plain { display: block; padding: 12px 14px; margin: 10px 0; font-size: 15px; word-break: break-all; border: 1px solid #4c3f78; }
        .flash { border: 1px solid #3f7a5a; background: #12291d; color: #c8f7d8; border-radius: 12px; padding: 12px 16px; margin: 0 0 18px; }
        .flash.error { border-color: #7a3f4b; background: #2a1218; color: #ffd4dc; }
        .flash.warn { border-color: #7a6a3f; background: #292412; color: #fff0c2; }
        .flash ul { margin: 6px 0 0; padding-left: 18px; }
        label { display: block; font-size: 13px; color: #c4b5fd; margin: 0 0 6px; }
        input[type="text"], input[type="email"], input[type="password"], select { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid #3b3260; background: #120e22; color: #f5f2ff; font: inherit; }
        input:focus, select:focus { outline: 2px solid #7c3aed; outline-offset: 1px; }
        .field { margin: 0 0 14px; }
        .checks { display: flex; gap: 16px; flex-wrap: wrap; margin: 0 0 14px; }
        .check { display: flex; align-items: center; gap: 7px; color: #c9c3dd; font-size: 14px; }
        .check input { width: 16px; height: 16px; }
        .btn { display: inline-block; padding: 10px 16px; border-radius: 10px; border: 1px solid #7c3aed; background: #7c3aed; color: #fff; text-decoration: none; font-weight: 650; font: inherit; font-weight: 650; cursor: pointer; }
        .btn:hover { background: #6d28d9; }
        .btn.secondary { background: transparent; border-color: #4c3f78; color: #ddd6fe; }
        .btn.danger { background: transparent; border-color: #7f3b4b; color: #ffb3c1; }
        .btn.small { padding: 7px 12px; font-size: 13px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #2a2342; vertical-align: top; }
        th { color: #a99fc4; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .06em; }
        tr:last-child td { border-bottom: 0; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; background: #241c40; color: #ddd6fe; margin: 0 4px 4px 0; }
        .badge.write { background: #3b1d52; color: #f0d9ff; }
        .badge.read { background: #1d2c52; color: #d9e6ff; }
        .auth-card { max-width: 420px; margin: 48px auto; }
        .entry-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
        .entry-item { border: 1px solid #332b52; background: #17122b; border-radius: 14px; padding: 18px 20px; }
        .entry-item h2 { margin: 0 0 6px; font-size: 17px; }
        .entry-item h2 a { color: #f5f2ff; text-decoration: none; }
        .entry-item h2 a:hover { color: #c4b5fd; }
        .meta-row { display: flex; flex-wrap: wrap; gap: 6px 14px; color: #8f86ab; font-size: 13px; margin: 0 0 8px; }
        .entry-text { color: #ddd6fe; margin: 0 0 10px; }
        .prose { color: #e7e1ff; line-height: 1.65; white-space: normal; }
        .version { border-left: 3px solid #4c3f78; padding-left: 14px; margin: 14px 0; }
        .version.current { border-left-color: #22c55e; }
        .version.outdated { border-left-color: #a16207; }
        .version h3 { margin: 0 0 4px; font-size: 16px; color: #f5f2ff; }
        footer { border-top: 1px solid #2a2342; color: #8f86ab; font-size: 13px; }
        .footer-inner { max-width: 980px; margin: 0 auto; padding: 22px 24px; }
        @media (max-width: 560px) { .wrap { padding: 24px 16px 48px; } .top-inner { padding: 14px 16px; } }
    </style>
</head>
<body>
<header class="top">
    <div class="top-inner">
        <a class="brand" href="{{ auth()->check() ? route('app.dashboard') : url('/') }}">Neiro<span>Brain</span></a>
        <nav>
            @auth
                <a href="{{ route('app.search') }}">Поиск</a>
                <a href="{{ route('app.projects') }}">Проекты</a>
                <a href="{{ route('app.graph') }}">Граф</a>
                <a href="{{ route('app.tokens.index') }}">Токены</a>
                <a href="{{ route('app.dashboard') }}">Профиль</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="link-button">Выход</button>
                </form>
            @else
                <a href="{{ route('login') }}">Вход</a>
            @endauth
        </nav>
    </div>
</header>

<main class="wrap">
    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="flash error">
            <strong>Проверьте поля:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

<footer>
    <div class="footer-inner">
        Neiro Brain — личная база знаний. Токены для MCP и API выпускаются здесь, у каждого свои.
    </div>
</footer>
</body>
</html>
