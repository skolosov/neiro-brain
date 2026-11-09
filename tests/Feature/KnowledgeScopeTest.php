<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Enums\KnowledgeScope;
use App\Models\KnowledgeEntry;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Services\Brain\ProjectContext;
use App\Services\Brain\SourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two invariants of the card model:
 *
 *  - a superseded card is history and must never reach the model;
 *  - what the user is like (profile, environment) must reach a chat about any
 *    project, otherwise he re-explains himself in every new chat.
 */
class KnowledgeScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $tags
     */
    private function write(
        string $title,
        string $content,
        string $kind = 'fact',
        ?string $project = null,
        KnowledgeScope $scope = KnowledgeScope::Project,
        ?string $summary = null,
    ): KnowledgeEntry {
        return app(KnowledgeWriter::class)->write(
            new ExtractedEntry(
                kind: KnowledgeKind::from($kind),
                title: $title,
                content: $content,
                summary: $summary,
                project: $project,
                scope: $scope,
                confidence: 1.0,
            ),
            source: app(SourceRegistry::class)->manual(),
        )['entry'];
    }

    public function test_superseded_card_is_hidden_from_every_query(): void
    {
        $old = $this->write(
            'Design dCell',
            'В dCell дизайн был строгий, светлая тема, зелёный акцент.',
            project: 'dcell',
        );

        $new = $this->write(
            'Design dCell',
            'В dCell дизайн строго Excel-like: белое полотно, серые линии, зелёный только для действий.',
            project: 'dcell',
        );

        $old->forceFill(['superseded_by' => $new->id])->save();

        $this->assertSame(1, KnowledgeEntry::query()->count());
        $this->assertNull(KnowledgeEntry::query()->find($old->id));

        // History is still there, and the chain is walkable.
        $this->assertSame(2, KnowledgeEntry::withoutGlobalScope('current')->count());
        $this->assertCount(2, KnowledgeEntry::historyOf($new->id));
    }

    public function test_profile_cards_reach_a_chat_about_another_project(): void
    {
        $this->write(
            'Action-подход',
            'Пользователь заворачивает каждое действие системы в отдельный Action-класс с DTO на входе.',
            kind: 'style',
            scope: KnowledgeScope::Profile,
        );

        $this->write(
            'Домен SMSGateway',
            'Department → LegalPerson → Sender → Template → Batch → Message.',
            project: 'smsgateway',
        );

        $context = app(ProjectContext::class)->overview('dcell');

        $profileTitles = array_column($context['profile'], 'title');

        $this->assertContains('Action-подход', $profileTitles);
        $this->assertNotContains(
            'Домен SMSGateway',
            array_column([...$context['profile'], ...($context['project']['cards'] ?? [])], 'title'),
        );
    }
}
