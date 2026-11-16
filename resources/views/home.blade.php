<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Neiro Brain — база знаний для LLM</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; background: #0f0c1d; color: #f5f2ff; line-height: 1.55; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 64px 24px; }
        .kicker { color: #c4b5fd; letter-spacing: .14em; text-transform: uppercase; font-size: 12px; }
        h1 { font-size: clamp(38px, 6vw, 64px); line-height: 1.05; margin: 12px 0 18px; }
        h1 span { color: #a78bfa; }
        p { color: #c9c3dd; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 36px; }
        .card { border: 1px solid #332b52; background: #17122b; border-radius: 16px; padding: 20px; }
        .card b { color: #ede9fe; }
        .card p { margin: 8px 0 0; font-size: 14px; }
        code { background: #241c40; padding: 2px 7px; border-radius: 6px; color: #ddd6fe; }
        .card code { display: inline-block; margin-bottom: 4px; font-size: 13px; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }
        a.btn { display: inline-block; padding: 12px 20px; border-radius: 10px; background: #7c3aed; color: white; text-decoration: none; font-weight: 650; }
        a.btn.secondary { background: transparent; border: 1px solid #4c3f78; color: #ddd6fe; }
        footer { margin-top: 56px; color: #8f86ab; font-size: 13px; }
        footer a { color: #a78bfa; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="kicker">MCP · BM25 · Laravel</div>
    <h1>Neiro Brain<br><span>второй мозг для LLM</span></h1>
    <p>
        База знаний принимает факты через MCP и HTTP API, держит их карточками с владельцем
        и отдаёт моделям. Поиск работает на Okapi BM25 с русской и английской морфологией,
        поэтому векторный индекс не нужен, чтобы вспомнить ваш стек, проекты и решения.
        У каждого человека свой токен и своя база — чужие карточки не видны ни в поиске, ни по id.
    </p>

    <div class="grid">
        <div class="card">
            <b>1. Подключите MCP</b>
            <p><code>{{ url('/mcp/knowledge') }}</code> отдаёт <code>search_knowledge</code>,
            <code>get_knowledge</code>, <code>remember_many</code> и другие инструменты.
            Нужен личный токен из кабинета.</p>
        </div>
        <div class="card">
            <b>2. Поставьте скилл <code>/brain</code></b>
            <p><code>php artisan brain:skill</code> кладёт протокол в харнесс: поиск перед ответом,
            запись пачкой, <code>/brain help</code> и <code>/brain compact</code> для слияния дублей.</p>
        </div>
        <div class="card">
            <b>3. Смотрите знания</b>
            <p>Кабинет: поиск, карточки с историей версий, проекты и граф.
            Админка MoonShine видит всех: люди, токены, карточки любого владельца.</p>
        </div>
    </div>

    <div class="actions">
        <a class="btn" href="{{ url('/app') }}">Открыть кабинет</a>
        <a class="btn secondary" href="{{ url('/admin') }}">Админка</a>
    </div>

    <footer>
        Знания пишутся через MCP, HTTP API и админку. Секреты вырезаются до записи в базу.
        Проверка живости — <a href="{{ url('/up') }}">/up</a>.
    </footer>
</div>
</body>
</html>
