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
        code { background: #241c40; padding: 2px 7px; border-radius: 6px; color: #ddd6fe; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }
        a.btn { display: inline-block; padding: 12px 20px; border-radius: 10px; background: #7c3aed; color: white; text-decoration: none; font-weight: 650; }
        a.btn.secondary { background: transparent; border: 1px solid #4c3f78; color: #ddd6fe; }
        footer { margin-top: 56px; color: #8f86ab; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="kicker">BM25 · MCP · Laravel AI SDK</div>
    <h1>Neiro Brain<br><span>второй мозг для LLM</span></h1>
    <p>
        Прозрачный прокси записывает переписку с любым харнессом, суммирует её в атомарные знания
        и отдаёт их обратно через MCP. Поиск работает на Okapi BM25 с русской и английской морфологией,
        поэтому модели не нужен векторный индекс, чтобы вспомнить ваш стек, проекты и решения.
    </p>

    <div class="grid">
        <div class="card"><b>1. Подключите прокси</b><br>Замените base URL на <code>{{ url('/v1') }}</code> — OpenAI- и Anthropic-клиенты работают без изменений.</div>
        <div class="card"><b>2. Подключите MCP</b><br>Сервер <code>{{ url('/mcp/knowledge') }}</code> отдаёт search_knowledge, get_knowledge, remember_knowledge и others.</div>
        <div class="card"><b>3. Смотрите знания</b><br>Админка MoonShine: чтение, ручное редактирование, статусы, источники и журнал поисковых запросов.</div>
    </div>

    <div class="actions">
        <a class="btn" href="{{ url('/admin') }}">Открыть админку</a>
        <a class="btn secondary" href="{{ url('/up') }}">Health check</a>
    </div>

    <footer>
        Каждые 15 минут: ingest → summarize → reindex. Секреты вырезаются до записи в базу.
    </footer>
</div>
</body>
</html>
