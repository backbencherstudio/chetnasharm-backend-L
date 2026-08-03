<?php

namespace Database\Factories;

use App\Models\AssignmentSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    protected $model = AssignmentSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_path' => 'assignment-submissions/example.pdf',
        ];
    }
}
