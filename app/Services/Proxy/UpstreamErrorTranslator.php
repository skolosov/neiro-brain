<?php

declare(strict_types=1);

namespace App\Services\Proxy;

use App\Services\Proxy\Dto\UpstreamConfig;

/**
 * Переводит отказ провайдера в объяснение, по которому можно действовать.
 *
 * Тело ответа upstream в чате бесполезно: «Failed to deserialize the JSON body
 * into the target type» не говорит ни что случилось, ни кто виноват, ни что
 * делать. Переводчик оставляет исходный текст в конце сообщения — он нужен,
 * когда причина новая, — но первым делом называет причину и действие.
 *
 * Статус тоже переводится. Ответ 401 от upstream значит «прокси не смог
 * представиться провайдеру», а не «клиент не авторизован»: свой ключ прокси
 * держит в настройках, и чинить его нужно там. Поэтому такой случай отдаётся
 * как 502, иначе харнесс показывает «Ключ API недействителен» и человек идёт
 * проверять ключ, который в порядке.
 */
final class UpstreamErrorTranslator
{
    /**
     * @return array{status: int, type: string, message: string}
     */
    public function translate(
        int $status,
        string $body,
        UpstreamConfig $upstream,
        int $normalized = 0,
    ): array {
        $driver = $upstream->driver !== '' ? $upstream->driver : 'upstream';
        $original = $this->originalMessage($body);
        $lower = mb_strtolower($original);

        if ($status === 401 || $status === 403 || str_contains($lower, 'api key') && str_contains($lower, 'invalid')) {
            return $upstream->keyFromRequest
                ? [
                    'status' => 401,
                    'type' => 'authentication_error',
                    'message' => "Провайдер {$driver} отклонил ключ, который прислал клиент (401). "
                        .'Обновите ключ в настройках подключения к модели. '
                        .'Другой вариант — оставить поле ключа в харнессе пустым и задать его в админке Neiro Brain: Настройки → AI-провайдер → «Ключ API».'
                        .$this->tail($original),
                ]
                : [
                    'status' => 502,
                    'type' => 'proxy_upstream_auth',
                    'message' => "Провайдер {$driver} не принял ключ из настроек Neiro Brain (401). "
                        .'Обновите ключ: админка → Настройки → AI-провайдер → «Ключ API». '
                        .'Если ключ верный, проверьте, что базовый URL ведёт на тот же аккаунт.'.$this->tail($original),
                ];
        }

        if ($status === 402 || str_contains($lower, 'insufficient balance') || str_contains($lower, 'quota')) {
            return [
                'status' => 402,
                'type' => 'proxy_quota',
                'message' => "У {$driver} закончились средства или квота (402). Пополните баланс у провайдера.".$this->tail($original),
            ];
        }

        if ($status === 429) {
            return [
                'status' => 429,
                'type' => 'rate_limit_error',
                'message' => "Провайдер {$driver} ограничил частоту запросов (429). Подождите и повторите.".$this->tail($original),
            ];
        }

        if (str_contains($lower, 'invalid type: null') || str_contains($lower, 'content: invalid type')) {
            $path = $this->nullPath($original);

            return [
                'status' => 422,
                'type' => 'proxy_invalid_null',
                'message' => "{$driver} не принимает null там, где ждёт строку"
                    .($path !== '' ? " (поле {$path})" : '')
                    .". Прокси исправил {$normalized} таких полей, но это осталось — значит null пришёл в месте, которое прокси не обходит. "
                    .'Пришлите эту строку вместе с логом: storage/logs/laravel.log, запись «Proxy upstream rejected the request».'
                    .$this->tail($original),
            ];
        }

        if (str_contains($lower, 'unknown variant') && str_contains($lower, 'developer')) {
            return [
                'status' => 422,
                'type' => 'proxy_unsupported_role',
                'message' => "Шлюз {$driver} не понимает роль «developer» в messages. "
                    .'Включите для модели compat.supportsDeveloperRole: false — тогда харнесс не будет её присылать.'.$this->tail($original),
            ];
        }

        if (str_contains($lower, 'context length') || str_contains($lower, 'maximum context') || str_contains($lower, 'too many tokens')) {
            return [
                'status' => 422,
                'type' => 'proxy_context_overflow',
                'message' => "Запрос не помещается в контекст модели {$upstream->model}. "
                    .'Сократите переписку или начните новый чат.'.$this->tail($original),
            ];
        }

        if ($status === 404) {
            return [
                'status' => 502,
                'type' => 'proxy_upstream_not_found',
                'message' => "Адрес {$upstream->baseUrl} не найден (404). "
                    .'Проверьте базовый URL провайдера в настройках: похоже, он ведёт на несуществующий путь.'.$this->tail($original),
            ];
        }

        if ($status >= 500) {
            return [
                'status' => 502,
                'type' => 'proxy_upstream_failed',
                'message' => "Провайдер {$driver} ответил {$status} — сбой на его стороне. "
                    .'Повторите через минуту; если повторяется, проверьте статус сервиса провайдера.'.$this->tail($original),
            ];
        }

        if (in_array($status, [400, 413, 422], true)) {
            return [
                'status' => $status,
                'type' => 'proxy_upstream_rejected',
                'message' => "Провайдер {$driver} отверг запрос ({$status}): причина в тексте ответа, прокси передал его без изменений.".$this->tail($original),
            ];
        }

        return [
            'status' => $status >= 400 ? $status : 502,
            'type' => 'proxy_upstream_error',
            'message' => "Провайдер {$driver} ответил {$status}.{$this->tail($original)}",
        ];
    }

    /**
     * Сообщение провайдера, если оно есть: сначала error.message, потом
     * message, иначе сырое тело.
     */
    private function originalMessage(string $body): string
    {
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            $message = $decoded['error']['message'] ?? $decoded['message'] ?? null;

            if (is_string($message) && trim($message) !== '') {
                return trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
    }

    private function tail(string $original): string
    {
        return $original === '' ? '' : ' Ответ провайдера: '.mb_substr($original, 0, 280);
    }

    /**
     * Путь вида messages[25].content из текста ошибки, чтобы сообщение
     * указывало на конкретное место, а не на весь запрос.
     */
    private function nullPath(string $original): string
    {
        if (preg_match('/([A-Za-z_]+\[\d+\](?:\.[A-Za-z_]+)+)/', $original, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/([A-Za-z_]+(?:\.[A-Za-z_]+)+):\s*invalid type: null/', $original, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }
}
