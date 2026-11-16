<?php

use App\Mcp\Servers\KnowledgeServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP servers
|--------------------------------------------------------------------------
|
| Web server: POST /mcp/knowledge (personal token from /app/tokens).
| Local server: `php artisan mcp:start knowledge` for stdio-based harnesses.
|
*/

Mcp::web('/mcp/knowledge', KnowledgeServer::class)
    ->middleware('brain.token:mcp');

Mcp::local('knowledge', KnowledgeServer::class);
