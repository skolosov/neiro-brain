@include('partials.brain-styles')

<div class="brain-ui nb-page">
    @if (session('brain_status'))
        <div class="nb-flash">{{ session('brain_status') }}</div>
    @endif

    @if ($errors->any())
        <div class="nb-flash nb-flash-error">
            <strong>Проверьте поля:</strong>
            <ul style="margin:.35rem 0 0; padding-left:1.1rem">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <h1 class="nb-title">Настройки Neiro Brain</h1>
        <p class="nb-subtitle">
            Настройки самой базы знаний. Личные токены для MCP и API выпускаются
            в панели, раздел «Токены» — у каждого человека свои.
        </p>
    </div>

    <form method="POST" action="{{ route('brain.admin.settings') }}" class="nb-form">
        @csrf

        <section class="nb-section">
            <h2>Правила для агента</h2>
            <p class="nb-help" style="margin-top:0;margin-bottom:1rem">
                Дополнительный текст дописывается в конец базовых правил, которые MCP-сервер
                отдаёт агенту при инициализации. Здесь же — заметка к skill-файлу.
            </p>

            <div class="nb-form-grid">
                <div>
                    <label class="nb-label" for="instructions_extra">Дополнительные правила для агента</label>
                    <textarea id="instructions_extra" name="instructions_extra" rows="7" class="nb-input" style="min-height:9rem;resize:vertical">{{ old('instructions_extra', $rules->instructions_extra) }}</textarea>
                    <p class="nb-help">Например: «Отвечай по-русски и всегда указывай, из какой карточки взят факт».</p>
                </div>
                <div>
                    <label class="nb-label" for="skill_note">Заметка к skill-файлу</label>
                    <textarea id="skill_note" name="skill_note" rows="5" class="nb-input" style="min-height:6rem;resize:vertical">{{ old('skill_note', $rules->skill_note) }}</textarea>
                    <p class="nb-help">Свободная заметка для себя: что стоит поправить в описании навыка.</p>
                </div>
            </div>
        </section>

        <section class="nb-section">
            <h2>Поиск</h2>
            <p class="nb-help" style="margin-top:0;margin-bottom:1rem">
                Параметры Okapi BM25 — по ним база ищет факты, которые отдаются через MCP
                и показываются в панели. Меняются на лету, без переиндексации.
            </p>
            <div class="nb-form-grid nb-form-grid-3">
                @foreach ([
                    ['retrieval_k1', 'k1', $retrieval->k1, '0.1', '3', '0.1', 'Насыщение повторов: 1.2 — прирост за второе-третье вхождение слова, после — почти нет. Больше — повторы весят сильнее.'],
                    ['retrieval_b', 'b (нормализация длины)', $retrieval->b, '0', '1', '0.05', '0.75 — штраф длинным статьям, короткая точная заметка весит больше. 0 — длина не важна.'],
                    ['retrieval_top_k', 'top K', $retrieval->top_k, '1', '50', '1', 'Сколько записей поиск возвращает по умолчанию.'],
                    ['retrieval_chunk_chars', 'Размер чанка, символов', $retrieval->chunk_chars, '300', '4000', '50', 'На сколько частей режется запись при индексации. Меньше — точнее, больше — шире контекст.'],
                    ['retrieval_chunk_overlap', 'Перекрытие, символов', $retrieval->chunk_overlap, '0', '1000', '10', 'Сколько символов соседние чанки делят между собой, чтобы мысль не рвалась на границе.'],
                    ['retrieval_freshness_weight', 'Бонус за свежесть', $retrieval->freshness_weight, '0', '1', '0.05', 'Насколько свежие знания весят больше старых (полураспад — 90 дней). 0 — отключено.'],
                    ['retrieval_importance_weight', 'Вес важности', $retrieval->importance_weight, '0', '0.5', '0.01', 'Насколько очки умножаются на важность записи (1–5). 0.08 — важность 5 даёт +16%.'],
                ] as [$name, $title, $value, $min, $max, $step, $hint])
                    <div>
                        <label class="nb-label">{{ $title }}</label>
                        <input type="number" name="{{ $name }}" value="{{ old($name, $value) }}" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" class="nb-input">
                        <p class="nb-help">{{ $hint }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="nb-section">
            <h2>Приватность</h2>
            <div class="nb-checks" style="margin-top:0">
                <label class="nb-check">
                    <input type="hidden" name="security_mask_secrets" value="0">
                    <input type="checkbox" name="security_mask_secrets" value="1" @checked($security->mask_secrets)>
                    Вырезать секреты из знаний
                </label>
                <label class="nb-check">
                    <input type="hidden" name="security_mask_emails" value="0">
                    <input type="checkbox" name="security_mask_emails" value="1" @checked($security->mask_emails)>
                    Скрывать email
                </label>
            </div>
        </section>

        <div class="nb-actions-bottom">
            <button type="submit" class="nb-btn nb-btn-primary" style="padding:.7rem 1.4rem">Сохранить настройки</button>
            <span class="nb-note">Дополнительные правила попадают в Instructions MCP сразу после сохранения.</span>
        </div>
    </form>
</div>
