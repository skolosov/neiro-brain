@php
    $tokenName = trim((string) ($name ?? ''));
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
                onclick="nbCopyToken(this)">Скопировать</button>
    </div>

    <p class="mt-1 text-xs opacity-70">
        Если кнопка не сработала (браузер запрещает доступ к буферу на http), поле уже выделено —
        нажмите Ctrl+C или ⌘+C.
    </p>
</div>

<script>
    function nbCopyToken(button) {
        var input = document.getElementById('nb-plain-token');
        var restore = function () {
            button.textContent = 'Скопировать';
        };
        var done = function () {
            button.textContent = 'Скопировано';
            window.setTimeout(restore, 2000);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(input.value).then(done).catch(function () {
                input.select();
                done();
            });
            return;
        }

        input.select();
        try {
            document.execCommand('copy');
            done();
        } catch (error) {
            button.textContent = 'Нажмите Ctrl+C';
            window.setTimeout(restore, 3000);
        }
    }
</script>
