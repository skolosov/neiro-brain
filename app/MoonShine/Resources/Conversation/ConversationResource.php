<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Conversation;

use App\Models\Conversation;
use App\MoonShine\Resources\Conversation\Pages\ConversationDetailPage;
use App\MoonShine\Resources\Conversation\Pages\ConversationFormPage;
use App\MoonShine\Resources\Conversation\Pages\ConversationIndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * @extends ModelResource<Conversation, ConversationIndexPage, ConversationFormPage, ConversationDetailPage>
 */
#[Icon('chat-bubble-left-right')]
#[Group('База знаний', 'book-open')]
#[Order(20)]
class ConversationResource extends ModelResource
{
    protected string $model = Conversation::class;

    protected string $title = 'Диалоги';

    protected string $column = 'title';

    protected bool $simplePaginate = true;

    protected function pages(): array
    {
        return [
            ConversationIndexPage::class,
            ConversationFormPage::class,
            ConversationDetailPage::class,
        ];
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::CREATE);
    }

    protected function search(): array
    {
        return ['id', 'title', 'project', 'harness'];
    }
}
