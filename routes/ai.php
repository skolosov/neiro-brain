<?php

use App\Mcp\Servers\KnowledgeServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP servers
|--------------------------------------------------------------------------
|
| Web server: POST /mcp/knowledge (personal token from the admin panel, Tokens section).
| Локального stdio-сервера больше нет: он ходил мимо токена, то есть мимо
| личности, и записывал знания случайному владельцу.
|
| OAuth: клиент без токена получает 401 с `WWW-Authenticate: … resource_metadata`,
| читает метаданные, регистрируется и открывает браузер на /oauth/authorize.
| Человек подтверждает доступ в панели, клиент дальше ходит с Bearer-токеном.
| Личный токен `nb-…` остаётся для клиентов без OAuth, API и CLI.
|
*/

Mcp::oauthRoutes();

Mcp::web('/mcp/knowledge', KnowledgeServer::class)
    ->middleware('brain.token:mcp');
