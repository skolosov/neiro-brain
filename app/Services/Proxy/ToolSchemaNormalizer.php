<?php

declare(strict_types=1);

namespace App\Services\Proxy;

/**
 * Normalizes tool JSON schemas before forwarding to strict gateways.
 *
 * OpenAI accepts an empty array for a no-argument tool, while DeepSeek (and
 * other strict OpenAI-compatible servers) require an object schema:
 *
 *     "parameters": []
 *     -> "parameters": {"type":"object","properties":{}}
 *
 * The proxy must not change tool semantics, only make the wire format valid.
 */
final class ToolSchemaNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeOpenAi(array $payload): array
    {
        if (! isset($payload['tools']) || ! is_array($payload['tools'])) {
            return $payload;
        }

        foreach ($payload['tools'] as $index => $tool) {
            if (! is_array($tool)) {
                continue;
            }

            if (isset($tool['function']) && is_array($tool['function'])) {
                $payload['tools'][$index]['function']['parameters'] = $this->objectSchema(
                    $tool['function']['parameters'] ?? [],
                );

                continue;
            }

            // Responses API uses a flat function tool shape.
            if (($tool['type'] ?? null) === 'function' && array_key_exists('parameters', $tool)) {
                $payload['tools'][$index]['parameters'] = $this->objectSchema($tool['parameters']);
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeAnthropic(array $payload): array
    {
        if (! isset($payload['tools']) || ! is_array($payload['tools'])) {
            return $payload;
        }

        foreach ($payload['tools'] as $index => $tool) {
            if (! is_array($tool)) {
                continue;
            }

            $payload['tools'][$index]['input_schema'] = $this->objectSchema(
                $tool['input_schema'] ?? [],
            );
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectSchema(mixed $schema): array
    {
        if (! is_array($schema) || $schema === []) {
            return [
                'type' => 'object',
                'properties' => (object) [],
            ];
        }

        if (! isset($schema['type'])) {
            $schema['type'] = 'object';
        }

        if (($schema['type'] ?? null) === 'object' && ! isset($schema['properties'])) {
            $schema['properties'] = (object) [];
        }

        if (isset($schema['properties']) && $schema['properties'] === []) {
            $schema['properties'] = (object) [];
        }

        return $schema;
    }
}
