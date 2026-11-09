@include('moonshine.brain.styles')

@php
    $reveal = fn (?string $token): string => $token !== null && $token !== '' ? $token : 'не задан';
@endphp

<div class="nb-page">
    @if (session('brain_status'))
        <div class="nb-flash">{{ session('brain_status') }}</div>
    @endif
    @if (session('brain_generated'))
        <div class="nb-flash">
            Новый {{ session('brain_generated') }}-токен создан. Скопируйте его и обновите в харнессе —
            старое значение больше не действует.
        </div>
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
            Провайдер, ключ и параметры модели приходят из харнесса транзитом.
            Здесь — токены доступа и настройки самой базы знаний.
        </p>
    </div>

    <form method="POST" action="{{ route('brain.admin.settings') }}" class="nb-form">
        @csrf

        <section class="nb-section">
            <h2>Токены доступа</h2>
            <p class="nb-help" style="margin-top:0;margin-bottom:1rem">
                Три независимых токена. Сгенерируйте свои, скопируйте в харнесс — здесь они больше не показываются полностью.
            </p>

            <div class="nb-token">
                <div class="nb-token-head">
                    <div>
                        <div class="nb-label" style="margin:0">Прокси-токен</div>
                        <div class="nb-help" style="margin:0">Заголовок <code>X-Neiro-Proxy-Token</code> для <code>/v1/*</code></div>
                    </div>
                    <div class="nb-token-value"><code id="nb-proxy-token">{{ $reveal($proxy->access_token) }}</code></div>
                </div>
                <div class="nb-token-actions">
                    <button type="button" class="nb-btn nb-btn-secondary" data-copy="nb-proxy-token">Скопировать</button>
                    <button type="submit" class="nb-btn nb-btn-secondary"
                            formaction="{{ route('brain.admin.tokens.generate') }}"
                            name="action_generate_token" value="proxy"
                            onclick="return confirm('Сгенерировать новый прокси-токен? Харнесс перестанет работать, пока не обновите токен в нём.')">
                        Сгенерировать новый
                    </button>
                </div>
            </div>

            <div class="nb-token">
                <div class="nb-token-head">
                    <div>
                        <div class="nb-label" style="margin:0">MCP-токен</div>
                        <div class="nb-help" style="margin:0">Заголовок <code>Authorization: Bearer</code> для <code>/mcp/knowledge</code></div>
                    </div>
                    <div class="nb-token-value"><code id="nb-mcp-token">{{ $reveal($security->mcp_token) }}</code></div>
                </div>
                <div class="nb-token-actions">
                    <button type="button" class="nb-btn nb-btn-secondary" data-copy="nb-mcp-token">Скопировать</button>
                    <button type="submit" class="nb-btn nb-btn-secondary"
                            formaction="{{ route('brain.admin.tokens.generate') }}"
                            name="action_generate_token" value="mcp"
                            onclick="return confirm('Сгенерировать новый MCP-токен? MCP-сервер в харнессе нужно будет обновить.')">
                        Сгенерировать новый
                    </button>
                </div>
            </div>

            <div class="nb-token">
                <div class="nb-token-head">
                    <div>
                        <div class="nb-label" style="margin:0">API-токен</div>
                        <div class="nb-help" style="margin:0">Заголовок <code>Authorization: Bearer</code> для <code>/api/v1/*</code></div>
                    </div>
                    <div class="nb-token-value"><code id="nb-api-token">{{ $reveal($security->api_token) }}</code></div>
                </div>
                <div class="nb-token-actions">
                    <button type="button" class="nb-btn nb-btn-secondary" data-copy="nb-api-token">Скопировать</button>
                    <button type="submit" class="nb-btn nb-btn-secondary"
                            formaction="{{ route('brain.admin.tokens.generate') }}"
                            name="action_generate_token" value="api"
                            onclick="return confirm('Сгенерировать новый API-токен? Внешние интеграции нужно будет обновить.')">
                        Сгенерировать новый
                    </button>
                </div>
            </div>
        </section>

        <section class="nb-section">
            <h2>Память и прокси</h2>
            <div class="nb-form-grid">
                <div>
                    <label class="nb-label">Top K памяти в промпте</label>
                    <input type="number" name="proxy_top_k" value="{{ old('proxy_top_k', $proxy->top_k) }}" min="1" max="20" class="nb-input">
                </div>
                <div>
                    <label class="nb-label">Лимит блока памяти, символов</label>
                    <input type="number" name="proxy_max_context_chars" value="{{ old('proxy_max_context_chars', $proxy->max_context_chars) }}" min="500" max="20000" class="nb-input">
                </div>
            </div>
            <div class="nb-checks">
                <label class="nb-check">
                    <input type="hidden" name="proxy_enabled" value="0">
                    <input type="checkbox" name="proxy_enabled" value="1" @checked($proxy->enabled)>
                    Прокси включён
                </label>
                <label class="nb-check">
                    <input type="hidden" name="proxy_inject_memory" value="0">
                    <input type="checkbox" name="proxy_inject_memory" value="1" @checked($proxy->inject_memory)>
                    Подмешивать память
                </label>
                <label class="nb-check">
                    <input type="hidden" name="proxy_capture" value="0">
                    <input type="checkbox" name="proxy_capture" value="1" @checked($proxy->capture)>
                    Записывать диалоги
                </label>
            </div>
        </section>

        <section class="nb-section">
            <h2>Поиск</h2>
            <p class="nb-help" style="margin-top:0;margin-bottom:1rem">
                Параметры Okapi BM25 — по ним база ищет факты, которые подмешиваются в промпт
                и отдаются через MCP. Меняются на лету, без переиндексации.
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
            <h2>Суммаризация</h2>
            <div class="nb-form-grid nb-form-grid-3">
                <div>
                    <label class="nb-label">Провайдер для извлечения знаний</label>
                    <select name="ai_provider" class="nb-select">
                        @foreach (['openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'deepseek' => 'DeepSeek', 'openrouter' => 'OpenRouter', 'ollama' => 'Ollama', 'gemini' => 'Gemini'] as $value => $name)
                            <option value="{{ $value }}" @selected($ai->provider === $value)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="nb-label">Модель</label>
                    <input type="text" name="ai_model" value="{{ old('ai_model', $ai->model) }}" class="nb-input">
                </div>
                <div>
                    <label class="nb-label">API-ключ</label>
                    <input type="password" name="ai_api_key" value="" class="nb-input" autocomplete="new-password"
                           placeholder="{{ $ai->api_key !== '' ? '•••••••• (оставьте пустым, чтобы не менять)' : 'не задан — работает эвристический экстрактор' }}">
                </div>
                <div>
                    <label class="nb-label">Base URL (необязательно)</label>
                    <input type="text" name="ai_base_url" value="{{ old('ai_base_url', $ai->base_url) }}" class="nb-input" placeholder="https://api.deepseek.com">
                </div>
                <div>
                    <label class="nb-label">Temperature</label>
                    <input type="number" step="0.05" min="0" max="2" name="ai_temperature" value="{{ old('ai_temperature', $ai->temperature) }}" class="nb-input">
                </div>
                <div>
                    <label class="nb-label">Max tokens</label>
                    <input type="number" min="256" max="200000" name="ai_max_tokens" value="{{ old('ai_max_tokens', $ai->max_tokens) }}" class="nb-input">
                </div>
            </div>
            <div class="nb-checks">
                <label class="nb-check">
                    <input type="hidden" name="ai_enabled" value="0">
                    <input type="checkbox" name="ai_enabled" value="1" @checked($ai->enabled)>
                    Использовать AI-провайдера (при ошибке — fallback на эвристику)
                </label>
            </div>
            <p class="nb-help">Ключ нужен только для суммаризации диалогов в cron, прокси его не использует.</p>
        </section>

        <section class="nb-section">
            <h2>Синхронизация</h2>
            <div class="nb-form-grid nb-form-grid-3">
                <div>
                    <label class="nb-label">Тишина перед суммаризацией, минут</label>
                    <input type="number" name="sync_quiet_minutes" value="{{ old('sync_quiet_minutes', $sync->quiet_minutes) }}" min="0" max="1440" class="nb-input">
                </div>
                <div>
                    <label class="nb-label">Диалогов за запуск</label>
                    <input type="number" name="sync_batch_size" value="{{ old('sync_batch_size', $sync->batch_size) }}" min="1" max="200" class="nb-input">
                </div>
                <div>
                    <label class="nb-label">Статус cron</label>
                    <label class="nb-check" style="margin-top:.55rem">
                        <input type="hidden" name="sync_enabled" value="0">
                        <input type="checkbox" name="sync_enabled" value="1" @checked($sync->enabled)>
                        Cron включён
                    </label>
                </div>
                <div>
                    <label class="nb-label">Хранить переписку, часов</label>
                    <input type="number" name="sync_transcript_retention_hours" value="{{ old('sync_transcript_retention_hours', $sync->transcript_retention_hours) }}" min="0" max="8760" class="nb-input">
                </div>
            </div>
            <div class="nb-checks">
                <label class="nb-check">
                    <input type="hidden" name="sync_auto_approve" value="0">
                    <input type="checkbox" name="sync_auto_approve" value="1" @checked($sync->auto_approve)>
                    Автоматически подтверждать извлечённые знания
                </label>
            </div>
            <p class="nb-help">
                Синхронизация раз в 15 минут через OpenServer-задачу <code>Laravel Schedule</code>.
                Переписка — только сырьё для суммаризации: после того как из неё сделаны карточки,
                сообщения удаляются (по умолчанию через 24 часа; <code>0</code> — не удалять).
                Сами диалоги остаются у провайдера, у нас — только карточки знаний и счётчики.
            </p>
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
            <span class="nb-note">Пустые секретные поля не перезаписываются.</span>
        </div>
    </form>
</div>

<script>
document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-copy]');
    if (!button) return;
    var source = document.getElementById(button.dataset.copy);
    if (!source) return;
    navigator.clipboard.writeText(source.textContent.trim()).then(function () {
        var label = button.textContent;
        button.textContent = 'Скопировано';
        setTimeout(function () { button.textContent = label; }, 1500);
    });
});
</script>
