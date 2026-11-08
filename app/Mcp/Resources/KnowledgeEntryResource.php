<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Models\KnowledgeEntry;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('knowledge-entry')]
#[MimeType('application/json')]
#[Description('Полная запись базы знаний по id: knowledge://entry/{id}.')]
class KnowledgeEntryResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('knowledge://entry/{id}');
    }

    public function handle(Request $request): Response
    {
        $id = (int) $request->get('id');
        $entry = KnowledgeEntry::query()->find($id);

        if ($entry === null) {
            return Response::error('Запись #'.$id.' не найдена.');
        }

        return Response::json([
            'id' => $entry->id,
            'title' => $entry->title,
            'content' => $entry->content,
            'details' => $entry->details,
            'kind' => $entry->kind->value,
            'status' => $entry->status->value,
            'project' => $entry->project,
            'tags' => $entry->tags ?? [],
            'importance' => $entry->importance,
            'confidence' => $entry->confidence,
            'occurred_at' => $entry->occurred_at?->toIso8601String(),
            'updated_at' => $entry->updated_at?->toIso8601String(),
        ]);
    }
}
