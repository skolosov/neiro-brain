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
        <h1 class="nb-title">Инструкция для агента</h1>
        <p class="nb-subtitle">
            Полный протокол работы с базой знаний. Этот текст сервер отдаёт MCP-ресурсом
            <code>knowledge://skill/brain</code>, поэтому правка в админке сразу меняет
            правила, которые читает агент. Кнопка установки скилла
            (<a href="{{ route('skill.install.ps1') }}">PowerShell</a>,
            <a href="{{ route('skill.install.sh') }}">sh</a>) ставит в харнесс файл скилла,
            который этот протокол читает.
        </p>
    </div>

    <form method="POST" action="{{ route('brain.admin.instruction') }}" class="nb-form">
        @csrf

        <section class="nb-section">
            <label class="nb-label" for="skill_text">Текст инструкции</label>
            <textarea id="skill_text" name="skill_text" rows="28" class="nb-input" style="min-height:32rem;resize:vertical;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace">{{ old('skill_text', $text) }}</textarea>
            <p class="nb-help">
                Символов: <span id="skill-text-count">0</span>.
                Пустой текст при сохранении означает сброс к стандартному протоколу из
                <code>resources/brain/skill.md</code>.
            </p>
        </section>

        <div class="nb-actions-bottom">
            <button type="submit" class="nb-btn nb-btn-primary" style="padding:.7rem 1.4rem">Сохранить</button>
            <span class="nb-note">После сохранения новый текст отдаётся ресурсом <code>knowledge://skill/brain</code>.</span>
        </div>
    </form>

    <form method="POST" action="{{ route('brain.admin.instruction.reset') }}">
        @csrf
        <div class="nb-actions-bottom">
            <button type="submit" class="nb-btn nb-btn-secondary">Сбросить к стандартному</button>
            <span class="nb-note">Вернёт текст из <code>resources/brain/skill.md</code> — эталон из репозитория.</span>
        </div>
    </form>
</div>

<script>
    (() => {
        const field = document.getElementById('skill_text');
        const counter = document.getElementById('skill-text-count');

        if (!field || !counter) {
            return;
        }

        const update = () => { counter.textContent = String(field.value.length); };

        field.addEventListener('input', update);
        update();
    })();
</script>
