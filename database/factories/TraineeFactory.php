<?php

namespace Database\Factories;

use App\Enums\DevelopmentStatus;
use App\Models\Store;
use App\Models\Trainee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trainee>
 */
class TraineeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->name(),
            'position' => fake()->randomElement(['Crew Member', 'Shift Lead', 'Cashier', 'Cook']),
            'hired_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'created_by' => null,
        ];
    }

    /**
     * Place the trainee in a specific store.
     */
    public function forStore(Store $store): static
    {
        return $this->state(fn (array $attributes) => [
            'store_id' => $store->id,
        ]);
    }

    /**
     * Retired from the active roster.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }

    /**
     * In the Development Zone at the given lifecycle status.
     */
    public function developmentStatus(DevelopmentStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'development_status' => $status,
        ]);
    }
}
