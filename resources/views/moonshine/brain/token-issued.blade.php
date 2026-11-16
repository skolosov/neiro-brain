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
    <pre class="mt-2 overflow-x-auto rounded bg-white p-2 text-xs dark:bg-dark-800"><code>{{ $plain }}</code></pre>
</div>
