<?php

use App\Mcp\Servers\KnowledgeServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP servers
|--------------------------------------------------------------------------
|
| Web server: POST /mcp/knowledge (personal token from /app/tokens).
| Локального stdio-сервера больше нет: он ходил мимо токена, то есть мимо
| личности, и записывал знания случайному владельцу.
|
*/

Mcp::web('/mcp/knowledge', KnowledgeServer::class)
    ->middleware('brain.token:mcp');
