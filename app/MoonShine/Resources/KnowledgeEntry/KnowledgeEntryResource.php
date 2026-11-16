<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\KnowledgeEntry;

use App\Models\KnowledgeEntry;
use App\MoonShine\Resources\KnowledgeEntry\Pages\KnowledgeEntryDetailPage;
use App\MoonShine\Resources\KnowledgeEntry\Pages\KnowledgeEntryFormPage;
use App\MoonShine\Resources\KnowledgeEntry\Pages\KnowledgeEntryIndexPage;
use App\Support\OwnerContext;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
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

    /** @var list<string> */
    protected array $with = ['owner'];

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

    /**
     * Список карточек в панели.
     *
     * Панель — единственный интерфейс, и в ней работают два режима: администратор
     * (контекст владельца пуст) видит всю базу, обычный человек — только свои
     * карточки. Ограничение стоит на запросе ресурса, а не на выдаче, поэтому
     * чужие строки не попадают даже в постраничную навигацию и поиск по таблице.
     */
    public function getQuery(): mixed
    {
        $query = parent::getQuery();

        if ($query instanceof EloquentBuilder) {
            $this->scopeToOwner($query);
        }

        return $query;
    }

    /**
     * Карточка по id открывается только у владельца: чужой id не находится, и
     * страница просмотра/редактирования отвечает 404, а не показывает данные.
     */
    protected function modifyItemQueryBuilder(BuilderContract $builder): BuilderContract
    {
        if ($builder instanceof EloquentBuilder) {
            $this->scopeToOwner($builder);
        }

        return $builder;
    }

    private function scopeToOwner(EloquentBuilder $builder): void
    {
        $context = app(OwnerContext::class);

        if (! $context->has()) {
            return;
        }

        $builder->where($builder->getModel()->qualifyColumn('owner_id'), $context->id());
    }
}
