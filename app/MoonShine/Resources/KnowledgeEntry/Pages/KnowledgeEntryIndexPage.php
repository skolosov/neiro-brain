<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\KnowledgeEntry\Pages;

use App\Enums\EntryStatus;
use App\Enums\KnowledgeKind;
use App\Models\User;
use App\MoonShine\Resources\KnowledgeEntry\KnowledgeEntryResource;
use App\MoonShine\Resources\User\UserResource;
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
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                'Владелец',
                'owner',
                formatted: static fn (User $user): string => (string) $user->name,
                resource: UserResource::class,
            ),
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
        return [
            Enum::make('Тип', 'kind')
                ->attach(KnowledgeKind::class)
                ->nullable(),
            Enum::make('Статус', 'status')
                ->attach(EntryStatus::class)
                ->nullable(),
            Text::make('Проект', 'project')->nullable(),
            Select::make('Владелец', 'owner_id')
                ->options(User::query()->orderBy('name')->pluck('name', 'id')->all())
                ->nullable(),
        ];
    }
}
