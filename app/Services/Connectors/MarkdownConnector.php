<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Models\KnowledgeSource;
use App\Services\Brain\ConversationIngestor;
use App\Services\Brain\Text;
use Illuminate\Support\Carbon;

/**
 * Imports a folder of Markdown notes as one conversation per file.
 */
final class MarkdownConnector implements Connector
{
    public function __construct(
        private readonly ConversationIngestor $ingestor,
    ) {}

    public function supports(KnowledgeSource $source): bool
    {
        return $source->type->value === 'markdown';
    }

    public function sync(KnowledgeSource $source): array
    {
        $path = (string) $source->configValue('path', '');
        $files = $this->files($path);
        $conversations = 0;
        $messages = 0;

        foreach ($files as $file) {
            $content = trim((string) file_get_contents($file));

            if ($content === '') {
                continue;
            }

            $title = $this->title($content, basename($file));
            $result = $this->ingestor->ingest(
                source: $source,
                externalId: hash('sha256', $file),
                messages: [[
                    'role' => 'user',
                    'content' => $content,
                    'occurred_at' => Carbon::createFromTimestamp((int) filemtime($file)),
                ]],
                attributes: [
                    'title' => $title,
                    'harness' => 'markdown',
                    'project' => $source->configValue('project'),
                    'metadata' => ['file' => $file],
                ],
            );

            $conversations++;
            $messages += $result['added'];
        }

        $source->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();

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
            if ($file->isFile() && in_array(strtolower($file->getExtension()), ['md', 'markdown', 'txt'], true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function title(string $content, string $fallback): string
    {
        if (preg_match('/^#\s+(.+)$/mu', $content, $matches) === 1) {
            return Text::limit(trim($matches[1]), 120);
        }

        return Text::limit(pathinfo($fallback, PATHINFO_FILENAME), 120);
    }
}
