<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'author_id' => User::factory()->reportWriter(),
            'title' => 'تقرير يومي ' . fake()->date('Y-m-d'),
            'content' => null,
            'ai_draft_content' => null,
            'generation_mode' => Report::MODE_MANUAL,
            'status' => Report::STATUS_DRAFT,
            'visible_to_monitors' => true,
            'report_date' => fake()->date('Y-m-d'),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => Report::STATUS_PUBLISHED,
            'published_at' => now(),
            'content' => 'محتوى منشور للاختبار',
        ]);
    }
}
