@php
    $tokenName = trim((string) ($name ?? ''));
    $mcpUrl = \App\Support\PublicEndpoint::mcp();
    $config = json_encode([
        'mcpServers' => [
            'neiro-brain' => [
                'url' => $mcpUrl,
                'headers' => ['Authorization' => 'Bearer '.$plain],
                'description' => 'Личная база знаний Neiro Brain: поиск карточек, чтение, запись новых знаний.',
                'exposure' => 'direct',
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<div class="rounded-lg border border-success bg-success/10 px-4 py-3 text-sm">
    <p class="font-semibold">
        @if ($tokenName !== '')
            Токен «{{ $tokenName }}» создан.
        @else
            Токен создан.
        @endif
    </p>
    <p class="mt-1">
        Скопируйте его сейчас — второй раз мы его не покажем. В базе хранится только SHA-256,
        восстановить значение нельзя.
    </p>

    <div class="mt-2 flex flex-wrap items-center gap-2">
        <input id="nb-plain-token" type="text" readonly value="{{ $plain }}"
               class="min-w-0 flex-1 rounded bg-white px-2 py-1 font-mono text-xs dark:bg-dark-800"
               onclick="this.select()">
        <button type="button" id="nb-copy-token" class="btn btn-primary btn-sm"
                onclick="nbCopyToken('nb-plain-token', this)">Скопировать</button>
    </div>

    <p class="mt-3 font-semibold">Или сразу готовый конфиг — вставьте его в харнесс:</p>
    <textarea id="nb-mcp-config" readonly rows="10"
              class="mt-1 w-full rounded bg-white px-2 py-1 font-mono text-xs dark:bg-dark-800">{{ $config }}</textarea>
    <div class="mt-2">
        <button type="button" id="nb-copy-config" class="btn btn-primary btn-sm"
                onclick="nbCopyToken('nb-mcp-config', this)">Скопировать конфиг</button>
    </div>

    <p class="mt-2 text-xs opacity-70">
        Конфиг содержит токен: не пересылайте его в чат, не показывайте агенту и не вставляйте в
        переписку. Если кнопка не сработала (браузер запрещает буфер на http), поле уже выделено —
        нажмите Ctrl+C или ⌘+C.
    </p>
</div>

<script>
    function nbCopyToken(elementId, button) {
        var field = document.getElementById(elementId);
        var restore = function () {
            button.textContent = button.dataset.label || 'Скопировать';
        };
        var done = function () {
            button.textContent = 'Скопировано';
            window.setTimeout(restore, 2000);
        };

        if (! button.dataset.label) {
            button.dataset.label = button.textContent;
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(field.value).then(done).catch(function () {
                field.select();
                done();
            });
            return;
        }

        field.select();
        try {
            document.execCommand('copy');
            done();
        } catch (error) {
            button.textContent = 'Нажмите Ctrl+C';
            window.setTimeout(restore, 3000);
        }
    }
</script>
