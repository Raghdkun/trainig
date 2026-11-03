<?php

namespace App\Enums;

/**
 * A trainee's position. Backed by the readable name (what's stored in
 * `trainees.position`) so every page that prints the position keeps working
 * unchanged, and so legacy free-text values can sit alongside these.
 */
enum Position: string
{
    case CrewMember = 'Crew Member';
    case CrewLeader = 'Crew Leader';
    case AssistantManager = 'Assistant Manager';

    public function code(): string
    {
        return match ($this) {
            self::CrewMember => 'CM',
            self::CrewLeader => 'CL',
            self::AssistantManager => 'AM',
        };
    }

    /** "CM – Crew Member", as shown in the position picker. */
    public function optionLabel(): string
    {
        return "{$this->code()} – {$this->value}";
    }

    /**
     * Whether this position can be picked for new trainees. Assistant Manager
     * is built in but switched off until TRAINING_ASSISTANT_MANAGER_ENABLED
     * is turned on — no code change needed to offer it.
     */
    public function isEnabled(): bool
    {
        return match ($this) {
            self::AssistantManager => (bool) config('training.assistant_manager_enabled'),
            default => true,
        };
    }

    /**
     * @return list<self>
     */
    public static function enabled(): array
    {
        return array_values(array_filter(self::cases(), fn (self $position): bool => $position->isEnabled()));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $position): array => [
            'value' => $position->value,
            'label' => $position->optionLabel(),
        ], self::enabled());
    }
}
