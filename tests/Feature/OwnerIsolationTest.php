<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Enums\KnowledgeScope;
use App\Mcp\Resources\KnowledgeEntryResource;
use App\Mcp\Resources\KnowledgeIndexResource;
use App\Mcp\Servers\KnowledgeServer;
use App\Mcp\Tools\DeleteKnowledgeTool;
use App\Mcp\Tools\GetKnowledgeTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ProjectContextTool;
use App\Mcp\Tools\RecentKnowledgeTool;
use App\Mcp\Tools\SearchKnowledgeTool;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\Dto\SearchQuery;
use App\Services\Brain\KnowledgeSearch;
use App\Services\Brain\KnowledgeWriter;
use App\Support\OwnerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

/**
 * Инварианты владельца знаний.
 *
 * Карточка принадлежит пользователю: чужой id не читается, не удаляется и не
 * находится поиском, а одинаковый текст у двух владельцев — две карточки,
 * а не одна. HTTP-запросы идут с личным токеном владельца.
 */
class OwnerIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function secondOwner(): User
    {
        return User::query()->create([
            'name' => 'Второй владелец',
            'email' => 'second@neiro-brain.test',
            'password' => Hash::make('password'),
        ]);
    }

    private function write(
        string $title,
        string $content,
        ?string $project = null,
        KnowledgeScope $scope = KnowledgeScope::Project,
    ): KnowledgeEntry {
        $result = app(KnowledgeWriter::class)->write(new ExtractedEntry(
            kind: KnowledgeKind::Fact,
            title: $title,
            content: $content,
            project: $project,
            scope: $scope,
            importance: 3,
            confidence: 1.0,
        ));

        $this->assertNotNull($result);

        return $result['entry'];
    }

    /** (а) поиск не находит карточку другого владельца. */
    public function test_search_does_not_see_another_owners_card(): void
    {
        $first = $this->owner();
        $this->withOwner($first);
        $this->write('Секрет первого', 'Ксилофонный термин принадлежит первому владельцу.');

        $second = $this->secondOwner();
        $this->withOwner($second);

        $result = app(KnowledgeSearch::class)->search(new SearchQuery('ксилофонный'));

        $this->assertTrue($result->isEmpty());

        // То же через сам инструмент: имя карточки не должно просочиться.
        $tool = KnowledgeServer::tool(SearchKnowledgeTool::class, ['query' => 'ксилофонный']);
        $tool->assertOk()->assertSee('Ничего не найдено')->assertDontSee('Секрет первого');

        // Своя карточка после возврата контекста находится.
        $this->withOwner($first);
        $result = app(KnowledgeSearch::class)->search(new SearchQuery('ксилофонный'));
        $this->assertNotEmpty($result->hits);
    }

    /** (б) get_knowledge по чужому id — ошибка. */
    public function test_get_knowledge_rejects_foreign_id(): void
    {
        $first = $this->owner();
        $this->withOwner($first);
        $card = $this->write('Карточка первого', 'Текст карточки первого владельца.');

        $this->withOwner($this->secondOwner());

        $response = KnowledgeServer::tool(GetKnowledgeTool::class, ['id' => $card->id]);

        $response->assertHasErrors(['не найдена']);
    }

    /** (в) delete_knowledge по чужому id — отказ, карточка на месте. */
    public function test_delete_knowledge_rejects_foreign_id(): void
    {
        $first = $this->owner();
        $this->withOwner($first);
        $card = $this->write('Удалять нельзя', 'Карточка первого владельца не должна удаляться чужими.');

        $this->withOwner($this->secondOwner());

        $response = KnowledgeServer::tool(DeleteKnowledgeTool::class, ['ids' => [$card->id]]);
        $response->assertHasErrors(['не найдены']);

        $fresh = KnowledgeEntry::withoutGlobalScope('owner')->find($card->id);

        $this->assertNotNull($fresh);
        $this->assertSame('approved', $fresh->status->value);
    }

    /** (г) одинаковый текст у двух владельцев — две карточки, не склейка. */
    public function test_same_content_belongs_to_each_owner_separately(): void
    {
        $text = 'Один и тот же текст, но у двух разных владельцев.';

        $this->withOwner($this->owner());
        $first = $this->write('Дубль', $text);

        $this->withOwner($this->secondOwner());
        $result = app(KnowledgeWriter::class)->write(new ExtractedEntry(
            kind: KnowledgeKind::Fact,
            title: 'Дубль',
            content: $text,
            importance: 3,
            confidence: 1.0,
        ));

        $this->assertNotNull($result);
        $this->assertTrue($result['created']);
        $this->assertNotSame($first->id, $result['entry']->id);

        $this->assertSame(
            2,
            KnowledgeEntry::withoutGlobalScope('owner')->where('content', $text)->count(),
        );
    }

    /** (д) запись без контекста владельца — исключение. */
    public function test_writer_without_owner_context_throws(): void
    {
        app(OwnerContext::class)->forget();

        $this->expectException(LogicException::class);

        app(KnowledgeWriter::class)->write(new ExtractedEntry(
            kind: KnowledgeKind::Fact,
            title: 'Без владельца',
            content: 'Такую карточку писать некуда.',
        ));
    }

    /** (е) N, avgdl и df в BM25 считаются только по корпусу владельца. */
    public function test_bm25_corpus_is_scoped_to_the_owner(): void
    {
        $first = $this->owner();
        $this->withOwner($first);
        $this->write('Стек первого', 'Laravel и PostgreSQL в первом корпусе.');
        $this->write('Вторая карточка первого', 'Docker и Redis в первом корпусе.');

        // Снимаем статистику до появления чужого корпуса.
        $before = app(KnowledgeSearch::class)->search(new SearchQuery('Laravel'));
        $this->assertNotEmpty($before->hits);
        $beforeScore = $before->hits[0]->score;
        $beforeCandidates = $before->candidates;

        $second = $this->secondOwner();
        $this->withOwner($second);

        // Пять карточек второго владельца, три из них — с тем же термином
        // Laravel и длинным текстом: если фильтр перенести на выдачу, изменятся
        // N, avgdl и df, а не только набор строк.
        $this->write('Редкий термин', 'Квазарный телескоп наблюдает далёкий квазар.');
        $this->write('Чужой Laravel 1', str_repeat('Laravel ', 40).'и ещё текст для длины.');
        $this->write('Чужой Laravel 2', str_repeat('Laravel ', 40).'другой длинный текст.');
        $this->write('Чужой Laravel 3', str_repeat('Laravel ', 40).'третий длинный текст.');
        $this->write('Второго владельца 5', 'Заметка про деплой и мониторинг.');

        $own = app(KnowledgeSearch::class)->search(new SearchQuery('квазарный'));
        $this->assertNotEmpty($own->hits);

        $this->withOwner($first);

        $foreign = app(KnowledgeSearch::class)->search(new SearchQuery('квазарный'));
        $this->assertTrue($foreign->isEmpty());

        // Общий термин первого корпуса по-прежнему находится, а его score и
        // число кандидатов не изменились: чужие чанки не попали в $base.
        $common = app(KnowledgeSearch::class)->search(new SearchQuery('Laravel'));
        $this->assertNotEmpty($common->hits);
        $this->assertSame('Стек первого', $common->hits[0]->entry->title);
        $this->assertSame($beforeCandidates, $common->candidates);
        $this->assertEqualsWithDelta($beforeScore, $common->hits[0]->score, 0.0001);
    }

    /** API не отдаёт чужую карточку по id: вместо этого 404. */
    public function test_api_show_returns_404_for_a_foreign_card(): void
    {
        // Карточку пишет второй владелец, запрос идёт с токеном первого:
        // владелец API теперь всегда берётся из токена.
        $this->withOwner($this->secondOwner());
        $foreign = $this->write('Чужая карточка', 'Эта карточка принадлежит второму владельцу.');

        $this->withOwner($this->owner());

        $this->getJson('/api/v1/entries/'.$foreign->id)
            ->assertNotFound();
    }

    /** list_projects перечисляет только проекты владельца. */
    public function test_list_projects_shows_only_own_projects(): void
    {
        $this->withOwner($this->owner());
        $this->write('Своя карточка проекта', 'Описание своего проекта.', 'alpha-project');

        $this->withOwner($this->secondOwner());
        $this->write('Чужая карточка проекта', 'Описание чужого проекта.', 'beta-project');

        $this->withOwner($this->owner());

        KnowledgeServer::tool(ListProjectsTool::class, [])
            ->assertOk()
            ->assertSee('alpha-project')
            ->assertDontSee('beta-project');
    }

    /** recent_knowledge не показывает чужие карточки. */
    public function test_recent_knowledge_shows_only_own_cards(): void
    {
        $this->withOwner($this->owner());
        $this->write('Своя свежая карточка', 'Свежий факт первого владельца.');

        $this->withOwner($this->secondOwner());
        $this->write('Чужая свежая карточка', 'Свежий факт второго владельца.');

        $this->withOwner($this->owner());

        KnowledgeServer::tool(RecentKnowledgeTool::class, ['days' => 365, 'limit' => 50])
            ->assertOk()
            ->assertSee('Своя свежая карточка')
            ->assertDontSee('Чужая свежая карточка');
    }

    /** get_project_context собирает профиль и проект только из своих карточек. */
    public function test_project_context_does_not_leak_foreign_cards(): void
    {
        $this->withOwner($this->owner());
        $this->write('Мой профиль', 'Первый владелец любит PHPUnit.', null, KnowledgeScope::Profile);
        $this->write('Моя проектная карточка', 'Факт про alpha-project.', 'alpha-project');

        $this->withOwner($this->secondOwner());
        $this->write('Чужой профиль', 'Второй владелец любит другой стек.', null, KnowledgeScope::Profile);
        $this->write('Чужая проектная карточка', 'Факт про beta-project.', 'beta-project');

        $this->withOwner($this->owner());

        KnowledgeServer::tool(ProjectContextTool::class, ['project' => 'alpha-project'])
            ->assertOk()
            ->assertSee('Мой профиль')
            ->assertSee('Моя проектная карточка')
            ->assertDontSee('Чужой профиль')
            ->assertDontSee('Чужая проектная карточка')
            ->assertDontSee('beta-project');
    }

    /** Ресурсы index и entry/{id} не отдают чужое. */
    public function test_knowledge_resources_do_not_expose_foreign_data(): void
    {
        $this->withOwner($this->owner());
        $own = $this->write('Своя карточка ресурса', 'Данные первого владельца для ресурса.');

        $this->withOwner($this->secondOwner());
        $foreign = $this->write('Чужая карточка ресурса', 'Данные второго владельца для ресурса.');

        $this->withOwner($this->owner());

        KnowledgeServer::resource(KnowledgeIndexResource::class)
            ->assertOk()
            ->assertSee('Своя карточка ресурса')
            ->assertDontSee('Чужая карточка ресурса');

        KnowledgeServer::resource(KnowledgeEntryResource::class, ['id' => $own->id])
            ->assertOk()
            ->assertSee('Своя карточка ресурса');

        KnowledgeServer::resource(KnowledgeEntryResource::class, ['id' => $foreign->id])
            ->assertHasErrors(['не найдена']);
    }

    /** REST-чтение: поиск, проекты и статистика считают только свои строки. */
    public function test_api_read_endpoints_return_only_own_data(): void
    {
        $this->withOwner($this->owner());
        $this->write('Своя API карточка', 'Описание своего API-проекта.', 'alpha-project');

        $this->withOwner($this->secondOwner());
        $this->write('Чужая API карточка', 'Описание чужого API-проекта.', 'beta-project');

        $this->withOwner($this->owner());

        $this->getJson('/api/v1/search?q=карточка')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Своя API карточка'])
            ->assertJsonMissing(['title' => 'Чужая API карточка']);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonFragment(['project' => 'alpha-project'])
            ->assertJsonMissing(['project' => 'beta-project']);

        $this->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonPath('data.entries', 1);
    }

    /** Удаление человека уносит его карточки и токены, чужие остаются. */
    public function test_deleting_a_user_removes_only_his_cards_and_tokens(): void
    {
        $first = $this->owner();
        $this->withOwner($first);
        $ownCard = $this->write('Карточка первого', 'Остаётся после удаления второго.');
        $ownToken = $this->issueToken($first);

        $second = $this->secondOwner();
        $this->withOwner($second);
        $foreignCard = $this->write('Карточка второго', 'Уходит вместе с владельцем.');
        $foreignToken = $this->issueToken($second);

        $second->delete();

        $this->assertDatabaseMissing('knowledge_entries', ['id' => $foreignCard->id]);
        $this->assertDatabaseMissing('api_tokens', ['id' => $foreignToken['token']->id]);

        $this->assertDatabaseHas('knowledge_entries', ['id' => $ownCard->id]);
        $this->assertDatabaseHas('api_tokens', ['id' => $ownToken['token']->id]);
    }
}
