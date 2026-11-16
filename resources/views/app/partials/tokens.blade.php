@php
    $plainToken = session('plain_token');
    $abilityLabels = ['read' => 'чтение', 'write' => 'запись'];
@endphp

<section class="card">
    <h2>Токены доступа</h2>
    <p>
        Токен открывает MCP <code>/mcp/knowledge</code> и HTTP API <code>/api/v1/*</code>
        от вашего имени. Передавайте его в заголовке <code>Authorization: Bearer …</code>
        или <code>x-api-key</code>.
    </p>

    @if ($plainToken)
        <div class="flash warn">
            <strong>Токен «{{ session('plain_token_name') }}» создан.</strong>
            Скопируйте его сейчас — второй раз мы его не покажем.
            <code class="token-plain" id="plain-token-value">{{ $plainToken }}</code>
            <button type="button" class="btn secondary small" data-copy-token="plain-token-value">Скопировать</button>
        </div>
    @endif

    @if ($tokens->isEmpty())
        <p class="muted">Пока нет ни одного токена. Выпустите первый — без него MCP и API закрыты.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Название</th>
                    <th>Способности</th>
                    <th>Создан</th>
                    <th>Использован</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($tokens as $token)
                    <tr>
                        <td>
                            <strong style="color:#ede9fe">{{ $token->name }}</strong>
                            @if ($token->expires_at)
                                <div class="muted">до {{ $token->expires_at->format('d.m.Y H:i') }}</div>
                            @endif
                        </td>
                        <td>
                            @foreach (($token->abilities ?? []) as $ability)
                                <span class="badge {{ $ability }}">{{ $abilityLabels[$ability] ?? $ability }}</span>
                            @endforeach
                            @if (in_array('write', $token->abilities ?? [], true))
                                <div class="muted">запись включает чтение</div>
                            @endif
                        </td>
                        <td>{{ $token->created_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>{{ $token->last_used_at?->format('d.m.Y H:i') ?? 'никогда' }}</td>
                        <td>
                            <form method="POST" action="{{ route('app.tokens.destroy', $token) }}"
                                  onsubmit="return confirm('Отозвать этот токен? Клиенты с ним перестанут работать.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn danger small">Отозвать</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 style="margin-top:26px">Выпустить новый токен</h2>
    <form method="POST" action="{{ route('app.tokens.store') }}">
        @csrf

        <div class="field">
            <label for="token-name">Название</label>
            <input type="text" id="token-name" name="name" value="{{ old('name') }}"
                   placeholder="Например: Ноутбук" required maxlength="64">
        </div>

        <div class="field">
            <label>Способности</label>
            <div class="checks">
                <label class="check">
                    <input type="checkbox" name="abilities[]" value="read"
                           @checked(in_array('read', old('abilities', ['read']), true))>
                    чтение
                </label>
                <label class="check">
                    <input type="checkbox" name="abilities[]" value="write"
                           @checked(in_array('write', old('abilities', []), true))>
                    запись (включает чтение)
                </label>
            </div>
        </div>

        <button type="submit" class="btn">Выпустить токен</button>
        <p class="muted" style="margin-top:10px">
            Плейн-токен покажется один раз. В базе хранится только его хеш — восстановить значение нельзя.
        </p>
    </form>
</section>

<script>
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-copy-token]');

        if (!button) {
            return;
        }

        var source = document.getElementById(button.dataset.copyToken);

        if (!source) {
            return;
        }

        var value = source.textContent.trim();
        var original = button.textContent;

        function report(text) {
            button.textContent = text;
            setTimeout(function () { button.textContent = original; }, 1500);
        }

        function legacyCopy() {
            var area = document.createElement('textarea');
            area.value = value;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.top = '-1000px';
            document.body.appendChild(area);
            area.select();

            var ok = false;

            try {
                ok = document.execCommand('copy');
            } catch (error) {
                ok = false;
            }

            document.body.removeChild(area);

            return ok;
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value)
                .then(function () { report('Скопировано'); })
                .catch(function () {
                    report(legacyCopy() ? 'Скопировано' : 'Не удалось — выделите и нажмите Ctrl+C');
                });

            return;
        }

        report(legacyCopy() ? 'Скопировано' : 'Не удалось — выделите и нажмите Ctrl+C');
    });
</script>
