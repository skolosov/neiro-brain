<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\KnowledgeEntry;

use App\Models\KnowledgeEntry;
use App\MoonShine\Resources\KnowledgeEntry\Pages\KnowledgeEntryDetailPage;
use App\MoonShine\Resources\KnowledgeEntry\Pages\KnowledgeEntryFormPage;
use App\MoonShine\Resources\KnowledgeEntry\Pages\KnowledgeEntryIndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ModelResource<KnowledgeEntry, KnowledgeEntryIndexPage, KnowledgeEntryFormPage, KnowledgeEntryDetailPage>
 */
#[Icon('book-open')]
#[Group('База знаний', 'book-open')]
#[Order(10)]
class KnowledgeEntryResource extends ModelResource
{
    protected string $model = KnowledgeEntry::class;

    protected string $title = 'Знания';

    protected string $column = 'title';

    protected bool $simplePaginate = true;

    protected function pages(): array
    {
        return [
            KnowledgeEntryIndexPage::class,
            KnowledgeEntryFormPage::class,
            KnowledgeEntryDetailPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'title', 'content', 'project'];
    }
}
