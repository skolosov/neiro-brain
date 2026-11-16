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

        .steps { display: flex; flex-direction: column; gap: 14px; margin-top: 36px; }
        .step { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 440px); gap: 8px 32px; align-items: start; border: 1px solid #332b52; background: #17122b; border-radius: 16px; padding: 20px 22px; }
        .step h2 { display: flex; align-items: center; gap: 10px; margin: 0 0 8px; font-size: 16px; color: #ede9fe; font-weight: 650; }
        .step h2 .step-num { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; flex: 0 0 24px; border-radius: 8px; background: #2a2150; color: #c4b5fd; font-size: 13px; font-weight: 700; }
        .step p { margin: 0; font-size: 14px; color: #c9c3dd; }
        .step p + p { margin-top: 8px; }
        .cmds { display: flex; flex-direction: column; gap: 10px; }
        .cmd-label { font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #8f86ab; margin-bottom: 5px; }
        code { font-family: ui-monospace, SFMono-Regular, "Cascadia Mono", Consolas, monospace; background: #241c40; color: #ddd6fe; border-radius: 6px; padding: 1px 6px; font-size: 12.5px; overflow-wrap: anywhere; }
        pre { margin: 0; background: #1b1533; border: 1px solid #2e2650; border-radius: 10px; padding: 10px 12px; }
        pre code { background: none; padding: 0; font-size: 12.5px; white-space: pre-wrap; overflow-wrap: anywhere; }

        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }
        a.btn { display: inline-block; padding: 12px 20px; border-radius: 10px; background: #7c3aed; color: white; text-decoration: none; font-weight: 650; }
        a.btn.secondary { background: transparent; border: 1px solid #4c3f78; color: #ddd6fe; }
        footer { margin-top: 56px; padding-top: 20px; border-top: 1px solid #241c3d; color: #8f86ab; font-size: 13px; display: flex; flex-wrap: wrap; gap: 8px 20px; }
        footer a { color: #a78bfa; text-decoration: none; }

        @media (max-width: 880px) {
            .step { grid-template-columns: minmax(0, 1fr); gap: 14px; }
        }
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

    <div class="steps">
        <section class="step">
            <div>
                <h2><span class="step-num">1</span> Подключите MCP</h2>
                <p>Сервер отдаёт <code>search_knowledge</code>, <code>get_knowledge</code>,
                    <code>remember_many</code> и другие инструменты. Нужен личный токен из кабинета.</p>
                <p>Проще не прописывать конфиг руками: после установки скилла наберите
                    <code>/brain init</code> — он спросит адрес и токен и подключит сервер сам.</p>
            </div>
            <div class="cmds">
                <div>
                    <div class="cmd-label">Адрес MCP</div>
                    <pre><code>{{ url('/mcp/knowledge') }}</code></pre>
                </div>
            </div>
        </section>

        <section class="step">
            <div>
                <h2><span class="step-num">2</span> Поставьте скилл</h2>
                <p>Скрипт делает одно: кладёт файл в <code>~/.agents/skills/brain</code>.
                    Дальше в харнессе — <code>/brain init</code>, перезапуск и <code>/brain doctor</code>.</p>
            </div>
            <div class="cmds">
                <div>
                    <div class="cmd-label">Windows</div>
                    <pre><code>irm {{ url('/skill/install.ps1') }} | iex</code></pre>
                </div>
                <div>
                    <div class="cmd-label">macOS и Linux</div>
                    <pre><code>curl -fsSL {{ url('/skill/install.sh') }} | sh</code></pre>
                </div>
            </div>
        </section>

        <section class="step">
            <div>
                <h2><span class="step-num">3</span> Смотрите знания</h2>
                <p>Кабинет: поиск, карточки с историей версий, проекты и граф. Админка MoonShine
                    видит всех: люди, токены, карточки любого владельца.</p>
                <p>Если что-то не работает — <code>/brain doctor</code>: он проверит скилл, конфиг,
                    токен и протокол и скажет, что именно сломано.</p>
            </div>
            <div class="cmds">
                <div>
                    <div class="cmd-label">Команды в харнессе</div>
                    <pre><code>/brain help
/brain init
/brain doctor</code></pre>
                </div>
            </div>
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
