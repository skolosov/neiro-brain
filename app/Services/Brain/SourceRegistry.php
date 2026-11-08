<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Enums\SourceType;
use App\Models\KnowledgeSource;

final class SourceRegistry
{
    public function proxy(): KnowledgeSource
    {
        return $this->firstOrCreate('proxy', 'Прокси чатов', SourceType::Proxy, [
            'description' => 'Разговоры, проходящие через OpenAI/Anthropic-совместимый прокси.',
        ]);
    }

    public function manual(): KnowledgeSource
    {
        return $this->firstOrCreate('manual', 'Ручные знания', SourceType::Manual, [
            'description' => 'Записи, добавленные вручную через админку, API или MCP.',
        ]);
    }

    public function forSlug(string $slug, string $name, SourceType $type, array $config = []): KnowledgeSource
    {
        return $this->firstOrCreate($slug, $name, $type, $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function firstOrCreate(string $slug, string $name, SourceType $type, array $config): KnowledgeSource
    {
        return KnowledgeSource::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'type' => $type->value,
                'enabled' => true,
                'config' => $config,
            ],
        );
    }
}
