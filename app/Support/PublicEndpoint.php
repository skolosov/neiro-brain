<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Адрес MCP-сервера для конфигов и документов.
 *
 * Берётся из `APP_URL`: это единственное место, где задан публичный адрес, и
 * админ машины один раз его прописывает. Если `APP_URL` пуст, падаем на адрес
 * текущего запроса — чтобы страница не отдавала пустой конфиг.
 */
final class PublicEndpoint
{
    public static function base(): string
    {
        $configured = rtrim(trim((string) config('app.url', '')), '/');

        if ($configured !== '') {
            return $configured;
        }

        return rtrim(url('/'), '/');
    }

    public static function mcp(): string
    {
        return self::base().'/mcp/knowledge';
    }
}
