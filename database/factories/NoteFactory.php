<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        $statuses = [Note::STATUS_DRAFT, Note::STATUS_PENDING, Note::STATUS_ACCEPTED, Note::STATUS_REJECTED];

        return [
            'user_id' => User::factory(),
            'floor_number' => fake()->numberBetween(1, 50),
            'camera_number' => fake()->numberBetween(1, 100),
            'observed_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'description' => fake()->sentence(10),
            'status' => Note::STATUS_DRAFT,
            'rejection_reason' => null,
            'processed_by' => null,
            'sent_at' => null,
            'processed_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Note::STATUS_DRAFT]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => Note::STATUS_PENDING,
            'sent_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => Note::STATUS_ACCEPTED,
            'sent_at' => now()->subDay(),
            'processed_by' => User::factory()->reportWriter(),
            'processed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => Note::STATUS_REJECTED,
            'sent_at' => now()->subDay(),
            'rejection_reason' => fake()->sentence(),
            'processed_by' => User::factory()->reportWriter(),
            'processed_at' => now(),
        ]);
    }
}
