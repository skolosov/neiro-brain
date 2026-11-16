<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KnowledgeKind;
use App\Enums\KnowledgeScope;
use App\Models\User;
use App\Services\Brain\Dto\ExtractedEntry;
use App\Services\Brain\KnowledgeWriter;
use App\Support\OwnerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

/**
 * Кабинет и админка рисуются одними партиалами.
 *
 * Проверяем три вещи: карточка размечена общим партиалом на обеих страницах
 * поиска, обе страницы живут в обёртке `.brain-ui`, и второй копии разметки
 * карточки (а также старого файла стилей) в репозитории нет.
 */
class ViewsSharedTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'name' => 'Admin',
            'email' => 'views@example.test',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => 1,
        ]);
    }

    private function user(): User
    {
        return User::query()->create([
            'name' => 'Человек',
            'email' => 'views-user@example.test',
            'password' => 'password',
            'locale' => 'ru',
            'is_active' => true,
        ]);
    }

    private function writeCard(User $owner, string $title): void
    {
        app(OwnerContext::class)->set($owner);

        app(KnowledgeWriter::class)->write(new ExtractedEntry(
            kind: KnowledgeKind::Stack,
            title: $title,
            content: 'Laravel 13, PostgreSQL и MoonShine.',
            project: 'neiro-brain',
            scope: KnowledgeScope::Project,
            importance: 4,
            confidence: 1.0,
        ));
    }

    public function test_search_pages_use_the_same_card_markup_and_wrapper(): void
    {
        $user = $this->user();
        $this->writeCard($user, 'Общая карточка');

        $cabinet = $this->actingAs($user)
            ->get('/app/search')
            ->assertOk()
            ->assertSee('Общая карточка')
            ->assertSee('data-brain-card', false);

        app(OwnerContext::class)->forget();

        $admin = $this->actingAs($this->admin(), 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'search-page']))
            ->assertOk()
            ->assertSee('Общая карточка')
            ->assertSee('data-brain-card', false);

        $this->assertMatchesRegularExpression('/class="[^"]*\bbrain-ui\b[^"]*"/', $cabinet->getContent());
        $this->assertMatchesRegularExpression('/class="[^"]*\bbrain-ui\b[^"]*"/', $admin->getContent());
    }

    public function test_projects_page_uses_the_shared_project_list(): void
    {
        $user = $this->user();
        $this->writeCard($user, 'Карточка проекта');

        $this->actingAs($user)
            ->get('/app/projects')
            ->assertOk()
            ->assertSee('neiro-brain')
            ->assertSee('nb-projects-grid', false);
    }

    public function test_card_markup_is_defined_in_one_shared_partial(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/moonshine/brain/styles.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/moonshine/brain/partials/knowledge-card.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/moonshine/brain/partials/entry-panel.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/moonshine/brain/partials/entry-links.blade.php'));

        $prefix = resource_path('views').DIRECTORY_SEPARATOR;
        $withMarker = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (str_contains((string) file_get_contents($file->getPathname()), 'data-brain-card')) {
                $withMarker[] = str_replace($prefix, '', $file->getPathname());
            }
        }

        $this->assertSame(
            ['partials'.DIRECTORY_SEPARATOR.'knowledge-card.blade.php'],
            array_values($withMarker),
        );
    }
}
