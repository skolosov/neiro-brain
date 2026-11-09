<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Merges a set of overlapping cards into a smaller set of theme cards.
 *
 * Repeated extraction runs describe the same theme in slightly different words,
 * which content-hash deduplication cannot catch. This agent is given the whole
 * set at once and asked to keep every fact while dropping the repetition.
 */
class ConsolidationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
Ты чистишь базу знаний: одни и те же факты попали в неё несколько раз,
сформулированные по-разному. Твоя задача — свести их в меньшее число карточек,
сохранив ВСЕ факты.

Правила:
1. Карточка — это тема, а не фраза. Своди близкие факты в одну карточку.
2. Ничего не теряй: каждый факт из исходных карточек должен оказаться в
   результате. Теряются только повторы и вода.
3. Противоречия разрешай в пользу более новой и более конкретной версии.
4. Не выдумывай того, чего не было в исходниках.
5. Заголовок — короткое имя темы. summary — 1–2 предложения (150–280 символов),
   что карточка утверждает.
6. theme — одно слово для группировки.
7. Чем меньше карточек при той же полноте — тем лучше.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'cards' => $schema->array()->items(
                $schema->object([
                    'title' => $schema->string()->required(),
                    'summary' => $schema->string()->required(),
                    'content' => $schema->string()->required(),
                    'theme' => $schema->string(),
                    'tags' => $schema->array()->items($schema->string()),
                    'importance' => $schema->integer()->min(1)->max(5)->required(),
                ])->withoutAdditionalProperties(),
            )->required(),
        ];
    }
}
