@include('partials.brain-styles')

<div class="brain-ui nb-page">
    @if (session('brain_status'))
        <div class="nb-flash">{{ session('brain_status') }}</div>
    @endif

    <div class="nb-header">
        <div>
            <h1 class="nb-title">Знания</h1>
            <p class="nb-subtitle">
                {{ $total }} карточек · профиль, проекты и окружение
                @if ($result['terms'] !== [])
                    · найдено по: {{ implode(', ', array_slice($result['terms'], 0, 8)) }}
                @endif
            </p>
        </div>
        <div class="nb-actions">
            <a href="{{ $graphUrl }}" class="nb-btn nb-btn-primary">Граф</a>
        </div>
    </div>

    @include('moonshine.brain.cards')
</div>
