<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Proxy\ToolSchemaNormalizer;
use PHPUnit\Framework\TestCase;

class ToolSchemaNormalizerTest extends TestCase
{
    public function test_empty_openai_tool_schema_becomes_an_object(): void
    {
        $normalizer = new ToolSchemaNormalizer;

        $payload = $normalizer->normalizeOpenAi([
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'get_goal',
                    'parameters' => [],
                ],
            ]],
        ]);

        $parameters = $payload['tools'][0]['function']['parameters'];

        $this->assertSame('object', $parameters['type']);
        $this->assertSame('{}', json_encode($parameters['properties']));
    }

    public function test_existing_openai_schema_is_preserved(): void
    {
        $normalizer = new ToolSchemaNormalizer;
        $schema = [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string']],
            'required' => ['query'],
        ];

        $payload = $normalizer->normalizeOpenAi([
            'tools' => [[
                'type' => 'function',
                'function' => ['name' => 'search', 'parameters' => $schema],
            ]],
        ]);

        $this->assertSame($schema, $payload['tools'][0]['function']['parameters']);
    }

    public function test_anthropic_empty_input_schema_becomes_an_object(): void
    {
        $normalizer = new ToolSchemaNormalizer;

        $payload = $normalizer->normalizeAnthropic([
            'tools' => [[
                'name' => 'get_goal',
                'description' => 'Get goal',
                'input_schema' => [],
            ]],
        ]);

        $this->assertSame('object', $payload['tools'][0]['input_schema']['type']);
    }
}
