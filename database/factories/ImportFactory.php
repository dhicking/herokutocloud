<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Import>
 */
class ImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'heroku_app_id' => fake()->uuid(),
            'heroku_app_name' => fake()->slug(2),
            'github_repository' => 'org/'.fake()->slug(1),
            'status' => Import::STATUS_PENDING,
        ];
    }

    public function phase1Running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Import::STATUS_PHASE1_RUNNING,
        ]);
    }

    public function phase1Done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Import::STATUS_PHASE1_DONE,
        ]);
    }

    public function phase2Running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Import::STATUS_PHASE2_RUNNING,
        ]);
    }

    public function phase2Done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Import::STATUS_PHASE2_DONE,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Import::STATUS_FAILED,
            'error_message' => fake()->sentence(),
        ]);
    }
}
