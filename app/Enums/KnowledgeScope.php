<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whose knowledge a card holds.
 *
 * The distinction is load-bearing: a project card must not be handed to a chat
 * working on another project, while profile and environment cards apply
 * everywhere. Without it the user's cross-project habits (how he runs commands,
 * which stack he uses) get buried under whichever project happened to be open
 * and the model re-asks for them in every new chat.
 */
enum KnowledgeScope: string
{
    /** The user himself: stack, architecture approach, what he likes and dislikes. */
    case Profile = 'profile';

    /** One repository: domain, structure, what is where. */
    case Project = 'project';

    /** The machines: where projects live and how commands are run on each one. */
    case Environment = 'environment';

    public function label(): string
    {
        return match ($this) {
            self::Profile => 'Про меня',
            self::Project => 'Проект',
            self::Environment => 'Окружение',
        };
    }

    /** Profiles and environments are cross-project and always apply. */
    public function isGlobal(): bool
    {
        return $this !== self::Project;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function toString(): string
    {
        return $this->label();
    }
}
