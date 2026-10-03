<?php

namespace App\Enums;

/**
 * Where a quiz link is in its lifecycle. A link only counts as started once
 * the trainee opens it and confirms it's meant for them — merely creating it
 * (or a chat app previewing it) doesn't.
 */
enum QuizAttemptStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
        };
    }
}
