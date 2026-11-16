<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Brain\Indexer;
use App\Settings\RetrievalSettings;
use App\Settings\RulesSettings;
use App\Settings\SecuritySettings;
use App\Settings\SettingsInitializer;
use App\Support\PanelAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Действия администратора базы знаний.
 *
 * Маршруты живут вне панели MoonShine, но доступны только администратору: у
 * обычного человека настроек и переиндексации нет, даже если он отправит POST
 * по адресу руками.
 */
class BrainAdminController extends Controller
{
    public function reindex(Indexer $indexer): RedirectResponse
    {
        $this->ensureAdmin();

        $count = $indexer->indexAll();

        return back()->with('brain_status', sprintf('Переиндексировано записей: %d.', $count));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        app(SettingsInitializer::class)->ensure();

        $validated = $request->validate([
            'retrieval_k1' => ['required', 'numeric', 'min:0.1', 'max:3'],
            'retrieval_b' => ['required', 'numeric', 'min:0', 'max:1'],
            'retrieval_top_k' => ['required', 'integer', 'min:1', 'max:50'],
            'retrieval_chunk_chars' => ['required', 'integer', 'min:300', 'max:4000'],
            'retrieval_chunk_overlap' => ['required', 'integer', 'min:0', 'max:1000'],
            'retrieval_freshness_weight' => ['required', 'numeric', 'min:0', 'max:1'],
            'retrieval_importance_weight' => ['required', 'numeric', 'min:0', 'max:0.5'],
            'security_mask_secrets' => ['nullable', 'boolean'],
            'security_mask_emails' => ['nullable', 'boolean'],
            'instructions_extra' => ['nullable', 'string', 'max:5000'],
            'skill_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $retrieval = app(RetrievalSettings::class);
        $retrieval->k1 = (float) $validated['retrieval_k1'];
        $retrieval->b = (float) $validated['retrieval_b'];
        $retrieval->top_k = (int) $validated['retrieval_top_k'];
        $retrieval->chunk_chars = (int) $validated['retrieval_chunk_chars'];
        $retrieval->chunk_overlap = (int) $validated['retrieval_chunk_overlap'];
        $retrieval->freshness_weight = (float) $validated['retrieval_freshness_weight'];
        $retrieval->importance_weight = (float) $validated['retrieval_importance_weight'];
        $retrieval->save();

        $security = app(SecuritySettings::class);
        $security->mask_secrets = $request->boolean('security_mask_secrets');
        $security->mask_emails = $request->boolean('security_mask_emails');
        $security->save();

        $rules = app(RulesSettings::class);
        $rules->instructions_extra = $this->textOrNull($validated['instructions_extra'] ?? null);
        $rules->skill_note = $this->textOrNull($validated['skill_note'] ?? null);
        $rules->save();

        return back()->with('brain_status', 'Настройки сохранены.');
    }

    private function textOrNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function ensureAdmin(): void
    {
        abort_unless(PanelAccess::isAdmin(), 403, 'Настройки базы знаний доступны только администратору.');
    }
}
