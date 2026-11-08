<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Models\KnowledgeSource;
use App\Services\Brain\ConversationIngestor;
use App\Services\Brain\Text;
use Illuminate\Support\Carbon;

/**
 * Imports the official ChatGPT data export (conversations.json).
 */
final class ChatGptConnector implements Connector
{
    public function __construct(
        private readonly ConversationIngestor $ingestor,
    ) {}

    public function supports(KnowledgeSource $source): bool
    {
        return $source->type->value === 'chatgpt';
    }

    public function sync(KnowledgeSource $source): array
    {
        $path = (string) $source->configValue('path', '');

        if ($path === '' || ! is_file($path)) {
            return ['conversations' => 0, 'messages' => 0];
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json)) {
            return ['conversations' => 0, 'messages' => 0];
        }

        $items = array_is_list($json) ? $json : [$json];
        $conversations = 0;
        $messages = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $parsed = $this->parseConversation($item);

            if ($parsed['messages'] === []) {
                continue;
            }

            $result = $this->ingestor->ingest(
                source: $source,
                externalId: $parsed['external_id'],
                messages: $parsed['messages'],
                attributes: [
                    'title' => $parsed['title'],
                    'harness' => 'chatgpt-export',
                    'project' => $source->configValue('project'),
                    'metadata' => ['source_file' => $path],
                ],
            );

            $conversations++;
            $messages += $result['added'];
        }

        $source->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();

        return ['conversations' => $conversations, 'messages' => $messages];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{external_id: string, title: string|null, messages: list<array{role: string, content: string, occurred_at: Carbon|null}>}
     */
    private function parseConversation(array $item): array
    {
        $title = isset($item['title']) ? Text::limit((string) $item['title'], 120) : null;
        $externalId = (string) ($item['id'] ?? $item['conversation_id'] ?? hash('sha256', (string) ($title ?? '').json_encode($item['create_time'] ?? '')));
        $mapping = $item['mapping'] ?? null;
        $messages = [];

        if (is_array($mapping)) {
            $nodes = [];

            foreach ($mapping as $node) {
                if (! is_array($node) || ! is_array($node['message'] ?? null)) {
                    continue;
                }

                $message = $node['message'];
                $role = Support::role($message);
                $content = Support::text($message['content']['parts'] ?? $message['content'] ?? '');

                if ($role === null || $content === '') {
                    continue;
                }

                $nodes[] = [
                    'role' => $role,
                    'content' => $content,
                    'occurred_at' => Support::occurredAt($message),
                    'order' => (float) ($message['create_time'] ?? 0),
                ];
            }

            usort($nodes, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

            foreach ($nodes as $node) {
                unset($node['order']);
                $messages[] = $node;
            }
        } elseif (isset($item['messages']) && is_array($item['messages'])) {
            foreach ($item['messages'] as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $role = Support::role($message);
                $content = Support::text($message['content'] ?? '');

                if ($role !== null && $content !== '') {
                    $messages[] = ['role' => $role, 'content' => $content, 'occurred_at' => Support::occurredAt($message)];
                }
            }
        }

        return ['external_id' => $externalId, 'title' => $title, 'messages' => $messages];
    }
}
