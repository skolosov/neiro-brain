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
        .wrap { max-width: 1040px; margin: 0 auto; padding: 64px 24px; }
        .kicker { color: #c4b5fd; letter-spacing: .14em; text-transform: uppercase; font-size: 12px; }
        h1 { font-size: clamp(38px, 6vw, 64px); line-height: 1.05; margin: 12px 0 18px; }
        h1 span { color: #a78bfa; }
        .lead { color: #c9c3dd; max-width: 76ch; margin: 0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 16px; margin-top: 36px; align-items: stretch; }
        .card { display: flex; flex-direction: column; gap: 12px; border: 1px solid #332b52; background: #17122b; border-radius: 16px; padding: 22px; }
        .card h2 { display: flex; align-items: center; gap: 10px; margin: 0; font-size: 16px; color: #ede9fe; font-weight: 650; }
        .card h2 .step { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; flex: 0 0 24px; border-radius: 8px; background: #2a2150; color: #c4b5fd; font-size: 13px; font-weight: 700; }
        .card p { margin: 0; font-size: 14px; color: #c9c3dd; }
        code { font-family: ui-monospace, SFMono-Regular, "Cascadia Mono", Consolas, monospace; background: #241c40; color: #ddd6fe; border-radius: 6px; padding: 1px 6px; font-size: 12.5px; overflow-wrap: anywhere; }
        pre { margin: 0; background: #1b1533; border: 1px solid #2e2650; border-radius: 10px; padding: 10px 12px; overflow-x: auto; }
        pre code { background: none; padding: 0; font-size: 12.5px; white-space: pre; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }
        a.btn { display: inline-block; padding: 12px 20px; border-radius: 10px; background: #7c3aed; color: white; text-decoration: none; font-weight: 650; }
        a.btn.secondary { background: transparent; border: 1px solid #4c3f78; color: #ddd6fe; }
        footer { margin-top: 56px; padding-top: 20px; border-top: 1px solid #241c3d; color: #8f86ab; font-size: 13px; display: flex; flex-wrap: wrap; gap: 8px 20px; }
        footer a { color: #a78bfa; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="kicker">MCP · BM25 · Laravel</div>
    <h1>Neiro Brain<br><span>второй мозг для LLM</span></h1>
    <p class="lead">
        База знаний принимает факты через MCP и HTTP API, держит их карточками с владельцем
        и отдаёт моделям. Поиск работает на Okapi BM25 с русской и английской морфологией,
        поэтому векторный индекс не нужен, чтобы вспомнить ваш стек, проекты и решения.
        У каждого человека свой токен и своя база — чужие карточки не видны ни в поиске, ни по id.
    </p>

    <div class="grid">
        <section class="card">
            <h2><span class="step">1</span> Подключите MCP</h2>
            <pre><code>{{ url('/mcp/knowledge') }}</code></pre>
            <p>Сервер отдаёт <code>search_knowledge</code>, <code>get_knowledge</code>,
                <code>remember_many</code> и другие инструменты. Нужен личный токен из кабинета.</p>
        </section>
        <section class="card">
            <h2><span class="step">2</span> Поставьте скилл</h2>
            <pre><code>php artisan brain:skill</code></pre>
            <p>Кладёт протокол в харнесс: поиск перед ответом, запись пачкой,
                <code>/brain help</code> и <code>/brain compact</code> для слияния дублей.</p>
        </section>
        <section class="card">
            <h2><span class="step">3</span> Смотрите знания</h2>
            <p>Кабинет: поиск, карточки с историей версий, проекты и граф. Админка MoonShine видит
                всех: люди, токены, карточки любого владельца.</p>
        </section>
    </div>

    <div class="actions">
        <a class="btn" href="{{ url('/app') }}">Открыть кабинет</a>
        <a class="btn secondary" href="{{ url('/admin') }}">Админка</a>
    </div>

    <footer>
        <span>Знания пишутся через MCP, HTTP API и админку. Секреты вырезаются до записи в базу.</span>
        <a href="{{ url('/up') }}">Проверка живости</a>
    </footer>
</div>
</body>
</html>
