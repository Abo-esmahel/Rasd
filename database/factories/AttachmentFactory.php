<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'note_id' => Note::factory(),
            
            'file_path' => 'notes/1/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word() . '.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(10000, 5000000),
        ];
    }

    public function image(): static
    {
        return $this->state(fn () => [
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(10000, 5000000),
        ]);
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'mime_type' => 'video/mp4',
            'file_size' => fake()->numberBetween(100000, 30000000),
        ]);
    }
}
