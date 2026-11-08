<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\KnowledgeSource;

use App\Models\KnowledgeSource;
use App\MoonShine\Resources\KnowledgeSource\Pages\KnowledgeSourceDetailPage;
use App\MoonShine\Resources\KnowledgeSource\Pages\KnowledgeSourceFormPage;
use App\MoonShine\Resources\KnowledgeSource\Pages\KnowledgeSourceIndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ModelResource<KnowledgeSource, KnowledgeSourceIndexPage, KnowledgeSourceFormPage, KnowledgeSourceDetailPage>
 */
#[Icon('circle-stack')]
#[Group('База знаний', 'book-open')]
#[Order(30)]
class KnowledgeSourceResource extends ModelResource
{
    protected string $model = KnowledgeSource::class;

    protected string $title = 'Источники';

    protected string $column = 'name';

    protected bool $simplePaginate = true;

    protected function pages(): array
    {
        return [
            KnowledgeSourceIndexPage::class,
            KnowledgeSourceFormPage::class,
            KnowledgeSourceDetailPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'name', 'slug'];
    }
}
