<?php

namespace Database\Factories;

use App\Models\StudentActivityNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentActivityNote>
 */
class StudentActivityNoteFactory extends Factory
{
    protected $model = StudentActivityNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment' => fake()->sentence(),
            'status' => fake()->randomElement(StudentActivityNote::STATUSES),
        ];
    }
}
