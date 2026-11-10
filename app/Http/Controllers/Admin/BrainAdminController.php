<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Brain\Indexer;
use App\Services\SummaryService;
use App\Services\SyncService;
use App\Settings\AiSettings;
use App\Settings\ProxySettings;
use App\Settings\RetrievalSettings;
use App\Settings\SecuritySettings;
use App\Settings\SettingsInitializer;
use App\Settings\SyncSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrainAdminController extends Controller
{
    public function sync(SyncService $sync): RedirectResponse
    {
        $stats = $sync->run(force: true);

        return back()->with('brain_status', sprintf(
            'Синхронизация завершена: обработано диалогов — %d, создано знаний — %d, обновлено — %d.',
            $stats['summarized'],
            $stats['entries_created'],
            $stats['entries_updated'],
        ));
    }

    public function reindex(Indexer $indexer): RedirectResponse
    {
        $count = $indexer->indexAll();

        return back()->with('brain_status', sprintf('Переиндексировано записей: %d.', $count));
    }

    public function summarize(Conversation $conversation, SummaryService $summaries): RedirectResponse
    {
        $result = $summaries->summarize($conversation, force: true);

        return back()->with('brain_status', sprintf(
            'Диалог #%d: %s, создано %d, обновлено %d.',
            $conversation->id,
            $result['status'],
            $result['created'],
            $result['updated'],
        ));
    }

    public function generateToken(): RedirectResponse
    {
        app(SettingsInitializer::class)->ensure();

        $token = 'nb-'.Str::random(40);

        $security = app(SecuritySettings::class);
        $security->access_token = $token;
        $security->save();

        return back()->with('brain_generated', 'access');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        app(SettingsInitializer::class)->ensure();

        $validated = $request->validate([
            'ai_enabled' => ['nullable', 'boolean'],
            'ai_provider' => ['required', 'string', 'max:32'],
            'ai_model' => ['required', 'string', 'max:128'],
            'ai_api_key' => ['nullable', 'string', 'max:512'],
            'ai_base_url' => ['nullable', 'url', 'max:255'],
            'ai_temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'ai_max_tokens' => ['required', 'integer', 'min:256', 'max:200000'],
            'retrieval_k1' => ['required', 'numeric', 'min:0.1', 'max:3'],
            'retrieval_b' => ['required', 'numeric', 'min:0', 'max:1'],
            'retrieval_top_k' => ['required', 'integer', 'min:1', 'max:50'],
            'retrieval_chunk_chars' => ['required', 'integer', 'min:300', 'max:4000'],
            'retrieval_chunk_overlap' => ['required', 'integer', 'min:0', 'max:1000'],
            'retrieval_freshness_weight' => ['required', 'numeric', 'min:0', 'max:1'],
            'retrieval_importance_weight' => ['required', 'numeric', 'min:0', 'max:0.5'],
            'proxy_enabled' => ['nullable', 'boolean'],
            'access_token' => ['nullable', 'string', 'max:255'],
            'proxy_inject_memory' => ['nullable', 'boolean'],
            'proxy_capture' => ['nullable', 'boolean'],
            'proxy_top_k' => ['required', 'integer', 'min:1', 'max:20'],
            'proxy_max_context_chars' => ['required', 'integer', 'min:500', 'max:20000'],
            'sync_enabled' => ['nullable', 'boolean'],
            'sync_quiet_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'sync_batch_size' => ['required', 'integer', 'min:1', 'max:200'],
            'sync_auto_approve' => ['nullable', 'boolean'],
            'sync_transcript_retention_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'security_mask_secrets' => ['nullable', 'boolean'],
            'security_mask_emails' => ['nullable', 'boolean'],
        ]);

        $ai = app(AiSettings::class);
        $ai->enabled = $request->boolean('ai_enabled');
        $ai->provider = $validated['ai_provider'];
        $ai->model = $validated['ai_model'];
        $ai->base_url = (string) ($validated['ai_base_url'] ?? '');
        $ai->temperature = (float) $validated['ai_temperature'];
        $ai->max_tokens = (int) $validated['ai_max_tokens'];

        if (($validated['ai_api_key'] ?? '') !== '') {
            $ai->api_key = $validated['ai_api_key'];
        }

        $ai->save();

        $retrieval = app(RetrievalSettings::class);
        $retrieval->k1 = (float) $validated['retrieval_k1'];
        $retrieval->b = (float) $validated['retrieval_b'];
        $retrieval->top_k = (int) $validated['retrieval_top_k'];
        $retrieval->chunk_chars = (int) $validated['retrieval_chunk_chars'];
        $retrieval->chunk_overlap = (int) $validated['retrieval_chunk_overlap'];
        $retrieval->freshness_weight = (float) $validated['retrieval_freshness_weight'];
        $retrieval->importance_weight = (float) $validated['retrieval_importance_weight'];
        $retrieval->save();

        $proxy = app(ProxySettings::class);
        $proxy->enabled = $request->boolean('proxy_enabled');
        $proxy->inject_memory = $request->boolean('proxy_inject_memory');
        $proxy->capture = $request->boolean('proxy_capture');
        $proxy->top_k = (int) $validated['proxy_top_k'];
        $proxy->max_context_chars = (int) $validated['proxy_max_context_chars'];

        $newToken = (string) ($validated['access_token'] ?? '');

        $proxy->save();

        $sync = app(SyncSettings::class);
        $sync->enabled = $request->boolean('sync_enabled');
        $sync->quiet_minutes = (int) $validated['sync_quiet_minutes'];
        $sync->batch_size = (int) $validated['sync_batch_size'];
        $sync->auto_approve = $request->boolean('sync_auto_approve');
        $sync->transcript_retention_hours = (int) $validated['sync_transcript_retention_hours'];
        $sync->save();

        $security = app(SecuritySettings::class);
        $security->mask_secrets = $request->boolean('security_mask_secrets');
        $security->mask_emails = $request->boolean('security_mask_emails');

        if ($newToken !== '') {
            $security->access_token = $newToken;
        }

        $security->save();

        return back()->with('brain_status', 'Настройки сохранены.');
    }
}
