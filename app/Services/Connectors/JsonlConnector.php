<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Models\KnowledgeSource;
use App\Services\Brain\ConversationIngestor;
use App\Services\Brain\Text;
use Illuminate\Support\Carbon;

/**
 * Reads Claude Code / Codex style JSONL transcripts.
 *
 * A line may look like:
 *   {"type":"user","message":{"role":"user","content":[{"type":"text","text":"..."}]}}
 *   {"type":"response_item","payload":{"type":"message","role":"user","content":[{"type":"input_text","text":"..."}]}}
 * or simply {"role":"user","content":"..."}.
 */
final class JsonlConnector implements Connector
{
    public function __construct(
        private readonly ConversationIngestor $ingestor,
    ) {}

    public function supports(KnowledgeSource $source): bool
    {
        return $source->type->value === 'jsonl';
    }

    public function sync(KnowledgeSource $source): array
    {
        $path = (string) $source->configValue('path', '');
        $files = $this->files($path);
        $conversations = 0;
        $messages = 0;

        foreach ($files as $file) {
            $parsed = $this->parseFile($file, $source);

            if ($parsed['messages'] === []) {
                continue;
            }

            $result = $this->ingestor->ingest(
                source: $source,
                externalId: $parsed['external_id'],
                messages: $parsed['messages'],
                attributes: [
                    'title' => $parsed['title'],
                    'harness' => (string) $source->configValue('harness', 'jsonl'),
                    'project' => $source->configValue('project'),
                    'metadata' => ['file' => $file],
                ],
            );

            $conversations++;
            $messages += $result['added'];
        }

        $source->forceFill([
            'last_synced_at' => now(),
            'last_error' => null,
        ])->save();

        return ['conversations' => $conversations, 'messages' => $messages];
    }

    /**
     * @return list<string>
     */
    private function files(string $path): array
    {
        if ($path === '') {
            return [];
        }

        if (is_file($path)) {
            return [$path];
        }

        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'jsonl') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array{external_id: string, title: string|null, messages: list<array{role: string, content: string, occurred_at: Carbon|null}>}
     */
    private function parseFile(string $file, KnowledgeSource $source): array
    {
        $messages = [];
        $title = null;
        $externalId = hash('sha256', $file);
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return ['external_id' => $externalId, 'title' => null, 'messages' => []];
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $row = json_decode($line, true);

                if (! is_array($row)) {
                    continue;
                }

                $externalId = Support::externalId($row, $externalId);
                $role = Support::role($row);
                $content = Support::text($row['message']['content'] ?? $row['content'] ?? $row['payload']['content'] ?? '');

                if ($role === null || $content === '') {
                    continue;
                }

                $messages[] = [
                    'role' => $role,
                    'content' => $content,
                    'occurred_at' => Support::occurredAt($row),
                ];

                if ($role === 'user' && $title === null) {
                    $title = Text::limit($content, 120);
                }
            }
        } finally {
            fclose($handle);
        }

        if ($title === null && $source->configValue('title') !== null) {
            $title = (string) $source->configValue('title');
        }

        return ['external_id' => $externalId, 'title' => $title, 'messages' => $messages];
    }
}
