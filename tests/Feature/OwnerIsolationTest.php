<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Mcp\Servers\KnowledgeServer;
use App\Mcp\Tools\DeleteKnowledgeTool;
use App\Mcp\Tools\GetKnowledgeTool;
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

    private function write(string $title, string $content, ?string $project = null): KnowledgeEntry
    {
        $result = app(KnowledgeWriter::class)->write(new ExtractedEntry(
            kind: KnowledgeKind::Fact,
            title: $title,
            content: $content,
            project: $project,
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

    /** (е) N и avgdl в BM25 считаются только по корпусу владельца. */
    public function test_bm25_corpus_is_scoped_to_the_owner(): void
    {
        $first = $this->owner();
        $this->withOwner($first);
        $this->write('Стек первого', 'Laravel и PostgreSQL в первом корпусе.');
        $this->write('Вторая карточка первого', 'Docker и Redis в первом корпусе.');

        $second = $this->secondOwner();
        $this->withOwner($second);

        // Пять карточек второго владельца: N и avgdl у него заметно другие.
        $this->write('Редкий термин', 'Квазарный телескоп наблюдает далёкий квазар.');
        $this->write('Второго владельца 2', 'Заметка про кэш и очереди.');
        $this->write('Второго владельца 3', 'Заметка про миграции и индексы.');
        $this->write('Второго владельца 4', 'Заметка про тесты и покрытие.');
        $this->write('Второго владельца 5', 'Заметка про деплой и мониторинг.');

        $own = app(KnowledgeSearch::class)->search(new SearchQuery('квазарный'));
        $this->assertNotEmpty($own->hits);

        $this->withOwner($first);

        $foreign = app(KnowledgeSearch::class)->search(new SearchQuery('квазарный'));
        $this->assertTrue($foreign->isEmpty());

        // Общий термин первого корпуса по-прежнему находится: статистика
        // считается по двум его карточкам, а не по семи карточкам инстанса.
        $common = app(KnowledgeSearch::class)->search(new SearchQuery('Laravel'));
        $this->assertNotEmpty($common->hits);
        $this->assertSame('Стек первого', $common->hits[0]->entry->title);
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
}
