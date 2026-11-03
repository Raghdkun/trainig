<?php

namespace App\Enums;

enum QuizQuestionType: string
{
    case Single = 'single';
    case Multi = 'multi';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single answer',
            self::Multi => 'Multiple answers',
        };
    }
}
