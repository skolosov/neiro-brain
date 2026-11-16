@php
    $status = trim((string) ($status ?? ''));
    $showOwner = (bool) ($showOwner ?? false);
    $ownerNames = (array) ($ownerNames ?? []);
@endphp

<div class="mb-3 rounded-lg border border-dark-200 px-4 py-3 text-sm dark:border-dark-600">
    <p class="font-semibold">Подключённые приложения</p>
    <p class="mt-1 text-xs opacity-70">
        Клиенты, которым вы разрешили доступ по OAuth. В их конфиге только адрес — токен они
        получают сами после входа в панель. Отзыв действует сразу: следующий запрос вернёт 401.
    </p>

    @if ($status !== '')
        <p class="mt-2 rounded border border-success bg-success/10 px-3 py-2">{{ $status }}</p>
    @endif

    @if ($apps->isEmpty())
        <p class="mt-2 opacity-70">Пока никто не подключён.</p>
    @else
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                <tr class="opacity-70">
                    <th class="py-1 pr-3">Приложение</th>
                    @if ($showOwner)
                        <th class="py-1 pr-3">Человек</th>
                    @endif
                    <th class="py-1 pr-3">Выдан</th>
                    <th class="py-1"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($apps as $app)
                    <tr class="border-t border-dark-200 dark:border-dark-600">
                        <td class="py-2 pr-3">{{ $app->client?->name ?? 'Приложение' }}</td>
                        @if ($showOwner)
                            <td class="py-2 pr-3">{{ $ownerNames[$app->user_id] ?? '—' }}</td>
                        @endif
                        <td class="py-2 pr-3">{{ $app->created_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td class="py-2 text-right">
                            <form method="POST"
                                  action="{{ route('brain.admin.oauth-tokens.destroy', ['token' => $app->id]) }}"
                                  onsubmit="return confirm('Отозвать доступ приложения?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-secondary">Отозвать</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

