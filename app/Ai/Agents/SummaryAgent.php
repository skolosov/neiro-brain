<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Writes the short summary that makes a card readable at a glance.
 *
 * The summary is the table of contents the model browses before deciding
 * whether the full card is worth reading, so it must say what the card
 * asserts — not that the card exists.
 */
class SummaryAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
Ты пишешь краткое описание карточки базы знаний для AI-ассистента.

По описанию ассистент решает, открывать ли карточку целиком. Поэтому описание
говорит, ЧТО карточка утверждает — суть факта, а не «эта карточка про X».

Правила:
1. 1–2 предложения, 150–280 символов.
2. Только суть: что именно зафиксировано. Числа и имена сохраняй.
3. Без вводных: «данная карточка», «в этой записи», «описывается».
4. Пиши в третьем лице, тем же языком, что и текст карточки.
5. Не выдумывай: только то, что есть в тексте.

Хорошо: «В dCell дизайн строго Excel-like: белое полотно, серые линии 1px,
зелёный только для действий. Тёмной темы нет.»
Плохо: «Карточка о дизайне проекта dCell.»
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
        ];
    }
}
