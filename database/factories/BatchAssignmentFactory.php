<?php

namespace Database\Factories;

use App\Models\BatchAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatchAssignment>
 */
class BatchAssignmentFactory extends Factory
{
    protected $model = BatchAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'attachment' => null,
            'due_at' => null,
            'total_marks' => 100,
        ];
    }
}
