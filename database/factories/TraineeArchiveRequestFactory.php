<?php

namespace Database\Factories;

use App\Enums\ArchiveRequestStatus;
use App\Models\Trainee;
use App\Models\TraineeArchiveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TraineeArchiveRequest>
 */
class TraineeArchiveRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trainee_id' => Trainee::factory(),
            'requested_by' => User::factory()->manager(),
            'reason' => fake()->sentence(),
            'status' => ArchiveRequestStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => ArchiveRequestStatus::Approved,
            'reviewed_by' => User::factory()->superAdmin(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(?string $note = null): static
    {
        return $this->state(fn (): array => [
            'status' => ArchiveRequestStatus::Rejected,
            'reviewed_by' => User::factory()->superAdmin(),
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }
}
