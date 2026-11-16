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
 * Интерфейс один — панель MoonShine, и он собирается из общих партиалов.
 *
 * Проверяем три вещи: страница поиска размечена общим партиалом карточки и
 * живёт в обёртке `.brain-ui`, проекты рисуются общим списком, а второй копии
 * разметки карточки (и папки кабинета, которого больше нет) в репозитории нет.
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

    public function test_search_page_uses_the_shared_card_markup_and_wrapper(): void
    {
        $this->writeCard($this->owner(), 'Общая карточка');

        app(OwnerContext::class)->forget();

        $response = $this->actingAs($this->admin(), 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'search-page']))
            ->assertOk()
            ->assertSee('Общая карточка')
            ->assertSee('data-brain-card', false);

        $this->assertMatchesRegularExpression('/class="[^"]*\bbrain-ui\b[^"]*"/', $response->getContent());
    }

    public function test_projects_page_uses_the_shared_project_list(): void
    {
        $this->writeCard($this->owner(), 'Карточка проекта');

        app(OwnerContext::class)->forget();

        $this->actingAs($this->admin(), 'moonshine')
            ->get(route('moonshine.page', ['pageUri' => 'projects-page']))
            ->assertOk()
            ->assertSee('neiro-brain')
            ->assertSee('nb-projects-grid', false);
    }

    public function test_card_markup_is_defined_in_one_shared_partial(): void
    {
        // Кабинета больше нет: его представления не должны вернуться.
        $this->assertDirectoryDoesNotExist(resource_path('views/app'));

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
