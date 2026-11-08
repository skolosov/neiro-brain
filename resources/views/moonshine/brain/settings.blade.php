@php
    $input = 'mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500 dark:border-gray-600 dark:bg-gray-900';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-200';
    $card = 'rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800';
@endphp

<div class="space-y-6">
    @if (session('brain_status'))
        <div class="rounded-lg border border-green-300 bg-green-50 p-4 text-green-900 dark:border-green-700 dark:bg-green-900/30 dark:text-green-100">
            {{ session('brain_status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-900 dark:border-red-700 dark:bg-red-900/30 dark:text-red-100">
            <b>Проверьте поля:</b>
            <ul class="mt-1 list-inside list-disc text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <h1 class="text-2xl font-bold">Настройки Neiro Brain</h1>
        <p class="text-sm text-gray-500">Секреты хранятся в таблице settings в зашифрованном виде (AES-256, ключ приложения).</p>
    </div>

    <form method="POST" action="{{ route('brain.admin.settings') }}" class="space-y-6">
        @csrf

        <section class="{{ $card }}">
            <h2 class="mb-4 text-lg font-semibold">Извлечение знаний (Laravel AI SDK)</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="{{ $label }}">Провайдер</label>
                    <select name="ai_provider" class="{{ $input }}">
                        @foreach (['openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'deepseek' => 'DeepSeek', 'openrouter' => 'OpenRouter', 'ollama' => 'Ollama', 'gemini' => 'Gemini'] as $value => $name)
                            <option value="{{ $value }}" @selected($ai->provider === $value)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Модель</label>
                    <input type="text" name="ai_model" value="{{ old('ai_model', $ai->model) }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="{{ $label }}">API-ключ</label>
                    <input type="password" name="ai_api_key" value="" placeholder="{{ $ai->api_key !== '' ? '•••••••• (оставьте пустым, чтобы не менять)' : 'не задан — работает эвристический экстрактор' }}" class="{{ $input }}" autocomplete="new-password">
                </div>
                <div>
                    <label class="{{ $label }}">Base URL (необязательно)</label>
                    <input type="text" name="ai_base_url" value="{{ old('ai_base_url', $ai->base_url) }}" class="{{ $input }}" placeholder="https://api.openai.com/v1">
                </div>
                <div>
                    <label class="{{ $label }}">Temperature</label>
                    <input type="number" step="0.05" min="0" max="2" name="ai_temperature" value="{{ old('ai_temperature', $ai->temperature) }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="{{ $label }}">Max tokens</label>
                    <input type="number" min="256" max="200000" name="ai_max_tokens" value="{{ old('ai_max_tokens', $ai->max_tokens) }}" class="{{ $input }}">
                </div>
            </div>
            <label class="mt-4 flex items-center gap-2 text-sm">
                <input type="hidden" name="ai_enabled" value="0">
                <input type="checkbox" name="ai_enabled" value="1" @checked($ai->enabled)>
                Использовать AI-провайдера (при ошибке — fallback на эвристику)
            </label>
        </section>

        <section class="{{ $card }}">
            <h2 class="mb-4 text-lg font-semibold">BM25 и чанки</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['retrieval_k1', 'k1', $retrieval->k1, '0.1', '3', '0.1'],
                    ['retrieval_b', 'b (нормализация длины)', $retrieval->b, '0', '1', '0.05'],
                    ['retrieval_top_k', 'top K', $retrieval->top_k, '1', '50', '1'],
                    ['retrieval_chunk_chars', 'Размер чанка, символов', $retrieval->chunk_chars, '300', '4000', '50'],
                    ['retrieval_chunk_overlap', 'Перекрытие, символов', $retrieval->chunk_overlap, '0', '1000', '10'],
                    ['retrieval_freshness_weight', 'Бонус за свежесть', $retrieval->freshness_weight, '0', '1', '0.05'],
                    ['retrieval_importance_weight', 'Вес важности', $retrieval->importance_weight, '0', '0.5', '0.01'],
                ] as [$name, $title, $value, $min, $max, $step])
                    <div>
                        <label class="{{ $label }}">{{ $title }}</label>
                        <input type="number" name="{{ $name }}" value="{{ old($name, $value) }}" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" class="{{ $input }}">
                    </div>
                @endforeach
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="mb-4 text-lg font-semibold">Прокси для харнессов</h2>
            <p class="mb-4 text-sm text-gray-500">
                База URL: <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">{{ url('/v1') }}</code>.
                Харнесс указывает её вместо api.openai.com / api.anthropic.com и передаёт токен из поля ниже.
            </p>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="{{ $label }}">Upstream</label>
                    <select name="proxy_upstream" class="{{ $input }}">
                        @foreach (['mock' => 'Mock (демо, без сети)', 'openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'deepseek' => 'DeepSeek', 'openrouter' => 'OpenRouter', 'ollama' => 'Ollama'] as $value => $name)
                            <option value="{{ $value }}" @selected($proxy->upstream === $value)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Модель по умолчанию</label>
                    <input type="text" name="proxy_model" value="{{ old('proxy_model', $proxy->model) }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="{{ $label }}">Base URL upstream (необязательно)</label>
                    <input type="text" name="proxy_base_url" value="{{ old('proxy_base_url', $proxy->base_url) }}" class="{{ $input }}" placeholder="https://api.deepseek.com/v1">
                </div>
                <div>
                    <label class="{{ $label }}">API-ключ upstream</label>
                    <input type="password" name="proxy_api_key" value="" placeholder="{{ $proxy->api_key !== '' ? '•••••••• (оставьте пустым, чтобы не менять)' : 'не задан' }}" class="{{ $input }}" autocomplete="new-password">
                </div>
                <div>
                    <label class="{{ $label }}">Bearer-токен для харнессов</label>
                    <input type="password" name="proxy_access_token" value="" placeholder="{{ $proxy->access_token !== '' ? '•••••••• (оставьте пустым, чтобы не менять)' : 'не задан' }}" class="{{ $input }}" autocomplete="new-password">
                </div>
                <div>
                    <label class="{{ $label }}">Top K памяти в промпте</label>
                    <input type="number" name="proxy_top_k" value="{{ old('proxy_top_k', $proxy->top_k) }}" min="1" max="20" class="{{ $input }}">
                </div>
                <div>
                    <label class="{{ $label }}">Лимит блока памяти, символов</label>
                    <input type="number" name="proxy_max_context_chars" value="{{ old('proxy_max_context_chars', $proxy->max_context_chars) }}" min="500" max="20000" class="{{ $input }}">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-6 text-sm">
                <label class="flex items-center gap-2">
                    <input type="hidden" name="proxy_enabled" value="0">
                    <input type="checkbox" name="proxy_enabled" value="1" @checked($proxy->enabled)>
                    Прокси включён
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="proxy_inject_memory" value="0">
                    <input type="checkbox" name="proxy_inject_memory" value="1" @checked($proxy->inject_memory)>
                    Подмешивать память
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="proxy_capture" value="0">
                    <input type="checkbox" name="proxy_capture" value="1" @checked($proxy->capture)>
                    Записывать диалоги
                </label>
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="mb-4 text-lg font-semibold">Синхронизация</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="{{ $label }}">Тишина перед суммаризацией, минут</label>
                    <input type="number" name="sync_quiet_minutes" value="{{ old('sync_quiet_minutes', $sync->quiet_minutes) }}" min="0" max="1440" class="{{ $input }}">
                </div>
                <div>
                    <label class="{{ $label }}">Диалогов за запуск</label>
                    <input type="number" name="sync_batch_size" value="{{ old('sync_batch_size', $sync->batch_size) }}" min="1" max="200" class="{{ $input }}">
                </div>
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="sync_enabled" value="0">
                        <input type="checkbox" name="sync_enabled" value="1" @checked($sync->enabled)>
                        Cron включён
                    </label>
                </div>
            </div>
            <label class="mt-4 flex items-center gap-2 text-sm">
                <input type="hidden" name="sync_auto_approve" value="0">
                <input type="checkbox" name="sync_auto_approve" value="1" @checked($sync->auto_approve)>
                Автоматически подтверждать извлечённые знания
            </label>
            <p class="mt-2 text-xs text-gray-500">Расписание: каждые 15 минут через <code>php artisan schedule:run</code> (Task Scheduler / cron).</p>
        </section>

        <section class="{{ $card }}">
            <h2 class="mb-4 text-lg font-semibold">Безопасность и токены</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="{{ $label }}">MCP-токен</label>
                    <input type="password" name="security_mcp_token" value="" placeholder="{{ $security->mcp_token !== '' ? '•••••••• (оставьте пустым, чтобы не менять)' : 'не задан' }}" class="{{ $input }}" autocomplete="new-password">
                </div>
                <div>
                    <label class="{{ $label }}">API-токен</label>
                    <input type="password" name="security_api_token" value="" placeholder="{{ $security->api_token !== '' ? '•••••••• (оставьте пустым, чтобы не менять)' : 'не задан' }}" class="{{ $input }}" autocomplete="new-password">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-6 text-sm">
                <label class="flex items-center gap-2">
                    <input type="hidden" name="security_mask_secrets" value="0">
                    <input type="checkbox" name="security_mask_secrets" value="1" @checked($security->mask_secrets)>
                    Вырезать секреты из знаний
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="security_mask_emails" value="0">
                    <input type="checkbox" name="security_mask_emails" value="1" @checked($security->mask_emails)>
                    Скрывать email
                </label>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-purple-600 px-6 py-3 font-semibold text-white hover:bg-purple-700">
                Сохранить настройки
            </button>
            <span class="text-sm text-gray-500">Пустые секретные поля не перезаписываются.</span>
        </div>
    </form>
</div>
