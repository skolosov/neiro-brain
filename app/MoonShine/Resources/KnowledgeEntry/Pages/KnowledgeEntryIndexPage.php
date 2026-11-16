<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\KnowledgeEntry\Pages;

use App\Enums\EntryStatus;
use App\Enums\KnowledgeKind;
use App\Models\User;
use App\MoonShine\Resources\KnowledgeEntry\KnowledgeEntryResource;
use App\MoonShine\Resources\User\UserResource;
use App\Support\PanelAccess;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<KnowledgeEntryResource>
 */
class KnowledgeEntryIndexPage extends IndexPage
{
    protected bool $isLazy = false;

    protected function fields(): iterable
    {
        $fields = [ID::make()->sortable()];

        // Колонка владельца имеет смысл, только когда видно чужие карточки:
        // у обычного человека в списке лишь его собственные строки.
        if (PanelAccess::isAdmin()) {
            $fields[] = BelongsTo::make(
                'Владелец',
                'owner',
                formatted: static fn (User $user): string => (string) $user->name,
                resource: UserResource::class,
            );
        }

        return [
            ...$fields,
            Text::make('Заголовок', 'title'),
            Enum::make('Тип', 'kind')
                ->attach(KnowledgeKind::class)
                ->badge(),
            Enum::make('Статус', 'status')
                ->attach(EntryStatus::class)
                ->badge(),
            Text::make('Проект', 'project'),
            Number::make('Важность', 'importance')->sortable(),
            Text::make('Обновлено', 'updated_at')->sortable(),
        ];
    }

    protected function filters(): iterable
    {
        $filters = [
            Enum::make('Тип', 'kind')
                ->attach(KnowledgeKind::class)
                ->nullable(),
            Enum::make('Статус', 'status')
                ->attach(EntryStatus::class)
                ->nullable(),
            Text::make('Проект', 'project')->nullable(),
        ];

        // Список людей в фильтре — это чужие имена, обычному человеку они не нужны.
        if (PanelAccess::isAdmin()) {
            $filters[] = Select::make('Владелец', 'owner_id')
                ->options(User::query()->orderBy('name')->pluck('name', 'id')->all())
                ->nullable();
        }

        return $filters;
    }
}
