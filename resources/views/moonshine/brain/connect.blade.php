@php
    /** @var string|null $plain */
    /** @var string $tokenName */
    /** @var bool $canIssue */
@endphp

<div class="nb-section">
    <h2>Подключение харнесса</h2>
    <p class="nb-help" style="margin-top:0;margin-bottom:1rem">
        Выпустите токен — страница покажет готовый конфиг MCP с адресом и ключом.
        Скопируйте блок целиком и вставьте в файл харнесса. Токен показывается один раз;
        в базе хранится только его хэш.
    </p>

    @if (! $canIssue)
        <p class="nb-help" style="color:#f87171">
            У вашей учётной записи нет связанного человека в базе знаний — токен выпускать некуда.
            Обратитесь к администратору.
        </p>
    @else
        <form method="POST" action="{{ route('connect.issue') }}" class="nb-form-grid">
            @csrf
            <div>
                <label class="nb-label" for="connect-name">Для чего токен</label>
                <input id="connect-name" name="name" type="text" class="nb-input"
                       placeholder="Ноутбук, рабочий сервер, DeepSeek Harness" maxlength="64">
                <p class="nb-help">По этому имени потом найдёте токен в разделе «Токены».</p>
            </div>
            <div>
                <button type="submit" class="nb-btn nb-btn-primary">Выпустить токен и показать конфиг</button>
            </div>
        </form>
    @endif
</div>

@if ($plain !== null)
    <div class="nb-section" style="margin-top:1rem">
        @include('moonshine.brain.token-issued', ['plain' => $plain, 'name' => $tokenName])
    </div>
@endif

<div class="nb-section" style="margin-top:1rem">
    <h2>Если харнесс умеет OAuth</h2>
    <p class="nb-help" style="margin-top:0">
        Таким клиентам токен не нужен: в конфиг пишется только адрес, а доступ человек
        подтверждает в браузере. Токен всё равно появится в разделе «Токены» как подключённое
        приложение, и его можно отозвать.
    </p>
</div>
