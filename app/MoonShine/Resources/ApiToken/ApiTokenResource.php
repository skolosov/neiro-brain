<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiToken;

use App\Models\ApiToken;
use App\MoonShine\Resources\ApiToken\Pages\ApiTokenFormPage;
use App\MoonShine\Resources\ApiToken\Pages\ApiTokenIndexPage;
use App\Services\Auth\TokenIssuer;
use App\Support\PanelAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;

/**
 * Токены доступа: свои — у каждого, все — у администратора.
 *
 * Человек выпускает токен сам и видит только свои строки; администратор видит и
 * отзывает любые, но чужих токенов не выпускает — это личное дело владельца.
 * Плейн `nb-…` показывается один раз на странице списка (flash-сообщение), в базе
 * остаётся только SHA-256, а отзыв — удаление строки.
 *
 * @extends ModelResource<ApiToken, ApiTokenIndexPage, ApiTokenFormPage, null>
 */
#[Icon('key')]
#[Group('Доступ', 'key')]
#[Order(20)]
class ApiTokenResource extends ModelResource
{
    protected string $model = ApiToken::class;

    protected string $title = 'Токены';

    protected string $column = 'name';

    protected bool $simplePaginate = true;

    /** @var list<string> */
    protected array $with = ['user'];

    /**
     * После выпуска возвращаемся к списку: именно там показывается плейн-токен.
     */
    protected ?PageType $redirectAfterSave = PageType::INDEX;

    public function getTitle(): string
    {
        return 'Токены';
    }

    protected function activeActions(): ListOf
    {
        // Выпуск доступен всем, у кого есть свой человек в базе: и обычному
        // пользователю, и администратору. Администратор без связки с человеком
        // (служебная учётка) не выпускает ничего — ему некому выдать токен.
        return PanelAccess::ownerId() !== null
            ? parent::activeActions()->only(Action::CREATE, Action::DELETE)
            : parent::activeActions()->only(Action::DELETE);
    }

    protected function pages(): array
    {
        return [
            ApiTokenIndexPage::class,
            ApiTokenFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'name'];
    }

    /**
     * Список токенов ограничен владельцем. Администратору контекст не нужен:
     * пустой `PanelAccess::ownerId()` не должен сузить его выборку до нуля.
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
     * Чужой id токена не открывается и не отзывается: строка не находится.
     */
    protected function modifyItemQueryBuilder(BuilderContract $builder): BuilderContract
    {
        if ($builder instanceof EloquentBuilder) {
            $this->scopeToOwner($builder);
        }

        return $builder;
    }

    /**
     * Создание токена для самого себя.
     *
     * Поля формы (имя и способности) уже проверены правилами страницы. Владельца
     * не берём из запроса: им становится человек, привязанный к текущему входу в
     * панель. Плейн-токен уходит во flash-сессию и показывается один раз.
     */
    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        if ($item->getKey() !== null) {
            return parent::save($item, $fields);
        }

        $owner = PanelAccess::owner();

        if ($owner === null) {
            throw new AuthorizationException('Выпустить токен может только вход, привязанный к человеку.');
        }

        $result = app(TokenIssuer::class)->issue(
            $owner,
            (string) request()->input('name', ''),
            (array) request()->input('abilities', []),
        );

        session()->flash('plain_token', $result['plain']);
        session()->flash('plain_token_name', $result['token']->name);

        return $this->getCaster()->cast($result['token']);
    }

    private function scopeToOwner(EloquentBuilder $builder): void
    {
        if (PanelAccess::isAdmin()) {
            return;
        }

        $ownerId = PanelAccess::ownerId();

        if ($ownerId === null) {
            // Вход без человека не должен видеть чужие токены даже теоретически:
            // middleware закрывает такой вход раньше, здесь — последний рубеж.
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($builder->getModel()->qualifyColumn('user_id'), $ownerId);
    }
}
