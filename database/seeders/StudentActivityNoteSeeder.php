<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\StudentActivityNote;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentActivityNoteSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed teacher activity notes for enrolled students.
     */
    public function run(): void
    {
        $teachers = Teacher::query()->with('batches')->orderBy('id')->get();
        $students = User::role('student', 'api')->orderBy('id')->get();

        if ($teachers->isEmpty() || $students->isEmpty()) {
            $this->command?->warn('StudentActivityNoteSeeder skipped: teachers or students missing. Run DemoDataSeeder first.');

            return;
        }

        $noteTemplates = [
            ['comment' => 'Participates actively in speaking drills.', 'status' => 'good'],
            ['comment' => 'Grammar is improving, needs more fluency practice.', 'status' => 'average'],
            ['comment' => 'Missed homework twice this week — follow up needed.', 'status' => 'needs_attention'],
            ['comment' => 'Struggling with pronunciation in longer sentences.', 'status' => 'bad'],
            ['comment' => 'Great progress on vocabulary retention.', 'status' => 'good'],
            ['comment' => 'Confidence is growing during pair activities.', 'status' => 'average'],
        ];

        foreach ($teachers as $teacherIndex => $teacher) {
            $batch = $teacher->batches->first();

            if (! $batch) {
                continue;
            }

            // Keep at least one active demo batch with a future window.
            if ($teacherIndex === 0) {
                $batch->update([
                    'status' => 'upcoming',
                    'active_status' => 1,
                    'start_date' => now()->addDays(7)->toDateString(),
                    'end_date' => now()->addDays(67)->toDateString(),
                ]);
            }

            $batchStudents = $students->slice($teacherIndex * 2, 2);

            if ($batchStudents->isEmpty()) {
                $batchStudents = $students->take(2);
            }

            foreach ($batchStudents->values() as $studentIndex => $student) {
                Enrollment::updateOrCreate(
                    [
                        'user_id' => $student->id,
                        'batch_id' => $batch->id,
                        'class_id' => $batch->class_id,
                    ],
                    [
                        'status' => 'active',
                        'enrolled_at' => now()->subDays(5),
                        'expiry_date' => $batch->end_date,
                    ]
                );

                $batch->update([
                    'filled_seat' => Enrollment::query()
                        ->where('batch_id', $batch->id)
                        ->where('status', 'active')
                        ->count(),
                ]);

                $template = $noteTemplates[($teacherIndex * 2 + $studentIndex) % count($noteTemplates)];

                StudentActivityNote::updateOrCreate(
                    [
                        'teacher_id' => $teacher->id,
                        'batch_id' => $batch->id,
                        'student_user_id' => $student->id,
                        'comment' => $template['comment'],
                    ],
                    [
                        'status' => $template['status'],
                    ]
                );

                // Second older/newer note for latest_note testing on first pair.
                if ($teacherIndex === 0 && $studentIndex === 0) {
                    StudentActivityNote::updateOrCreate(
                        [
                            'teacher_id' => $teacher->id,
                            'batch_id' => $batch->id,
                            'student_user_id' => $student->id,
                            'comment' => 'Follow-up: clearer pronunciation this session.',
                        ],
                        [
                            'status' => 'good',
                        ]
                    );
                }
            }
        }
    }
}
