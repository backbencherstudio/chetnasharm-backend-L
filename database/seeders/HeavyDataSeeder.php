<?php

namespace Database\Seeders;

use App\Models\StudentActivityNote;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/** Bulk-seeds large volumes of domain data for local/staging. Delete before production. */
class HeavyDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private int $chunk = 500;

    private string $passwordHash;

    private string $now;

    private int $teacherRoleId;

    private int $studentRoleId;

    private int $paymentSeq = 1;

    public function run(): void
    {
        $this->passwordHash = Hash::make('12345678');
        $this->now = now()->toDateTimeString();
        $this->paymentSeq = 1;

        $this->teacherRoleId = (int) Role::query()->where('name', 'teacher')->where('guard_name', 'api')->value('id');
        $this->studentRoleId = (int) Role::query()->where('name', 'student')->where('guard_name', 'api')->value('id');

        if ($this->teacherRoleId === 0 || $this->studentRoleId === 0) {
            $this->command?->error('Roles missing. Run RolePermissionSeeder first.');

            return;
        }

        $this->command?->info('HeavyDataSeeder starting…');

        $teacherIds = $this->seedTeachers(100);
        $studentIds = $this->seedStudents(15000);
        $classIds = $this->seedClasses(50);
        $batches = $this->seedBatches($classIds, $teacherIds, 6);
        $this->seedSchedulesAndAvailability($batches, $teacherIds);
        $enrollmentPairs = $this->seedEnrollmentsAndPayments($batches, $studentIds, 350);
        $this->seedAttendances($enrollmentPairs, 12);
        $this->seedStudentActivityNotes($batches, $studentIds, 50000);
        $this->seedAssignmentsAndSubmissions($batches, $enrollmentPairs, 4);
        $this->seedTeacherNotesAndRecordings($batches);
        $this->seedWaitlists($batches, $studentIds, 5000);
        $this->seedNotificationLogs($batches, $studentIds, 20000);
        $this->seedPasswordOtpsAndResets($studentIds);
        $this->seedAdminDirectPermissions();
        $this->seedFrameworkTables($studentIds);

        $this->command?->info('HeavyDataSeeder finished.');
    }

    /** @return list<int> */
    private function seedTeachers(int $count): array
    {
        $this->command?->info("Seeding {$count} teachers…");

        $userRows = [];
        $roleRows = [];
        $emails = [];

        for ($i = 1; $i <= $count; $i++) {
            $email = "heavy.teacher{$i}@example.com";
            $emails[] = $email;
            $userRows[] = [
                'name' => fake()->name(),
                'email' => $email,
                'department' => 'Teaching',
                'mobile' => '018'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'email_verified_at' => $this->now,
                'password' => $this->passwordHash,
                'suspend_status' => 0,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            if (count($userRows) >= $this->chunk) {
                DB::table('users')->insert($userRows);
                $userRows = [];
            }
        }

        if ($userRows !== []) {
            DB::table('users')->insert($userRows);
        }

        $userIds = DB::table('users')->whereIn('email', $emails)->orderBy('id')->pluck('id');

        $teacherRows = [];
        $roleRows = [];

        foreach ($userIds as $userId) {
            $teacherRows[] = [
                'user_id' => $userId,
                'country' => fake()->country(),
                'timezone' => fake()->timezone(),
                'qualification' => fake()->randomElement(['BA English', 'MA Linguistics', 'CELTA', 'TESOL']),
                'expertise' => fake()->randomElement(['Spoken English', 'IELTS', 'Business English', 'Grammar']),
                'years_of_exp' => fake()->numberBetween(1, 15),
                'bio' => fake()->sentence(12),
                'about' => fake()->paragraph(),
                'specializations' => json_encode(['Speaking', 'Writing']),
                'languages_spoken' => json_encode(['English', 'Bengali']),
                'courses_can_teach' => json_encode(['Spoken English']),
                'interests' => json_encode(['Teaching', 'Travel']),
                'is_top' => fake()->boolean(15) ? 1 : 0,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            $roleRows[] = [
                'role_id' => $this->teacherRoleId,
                'model_type' => User::class,
                'model_id' => $userId,
            ];

            if (count($teacherRows) >= $this->chunk) {
                DB::table('teachers')->insert($teacherRows);
                DB::table('model_has_roles')->insert($roleRows);
                $teacherRows = [];
                $roleRows = [];
            }
        }

        if ($teacherRows !== []) {
            DB::table('teachers')->insert($teacherRows);
            DB::table('model_has_roles')->insert($roleRows);
        }

        return DB::table('teachers')->whereIn('user_id', $userIds)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<int> */
    private function seedStudents(int $count): array
    {
        $this->command?->info("Seeding {$count} students…");

        $userRows = [];
        $chunkEmails = [];

        for ($i = 1; $i <= $count; $i++) {
            $email = "heavy.student{$i}@example.com";
            $chunkEmails[] = $email;
            $userRows[] = [
                'name' => fake()->name(),
                'email' => $email,
                'department' => 'Student',
                'mobile' => '019'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'email_verified_at' => $this->now,
                'password' => $this->passwordHash,
                'suspend_status' => 0,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            if (count($userRows) >= $this->chunk) {
                $this->insertStudentChunk($userRows, $chunkEmails);
                $userRows = [];
                $chunkEmails = [];
            }
        }

        if ($userRows !== []) {
            $this->insertStudentChunk($userRows, $chunkEmails);
        }

        return DB::table('users')
            ->where('email', 'like', 'heavy.student%@example.com')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $userRows
     * @param  list<string>  $emails
     */
    private function insertStudentChunk(array $userRows, array $emails): void
    {
        DB::table('users')->insert($userRows);

        $roleRows = DB::table('users')
            ->whereIn('email', $emails)
            ->pluck('id')
            ->map(fn ($userId) => [
                'role_id' => $this->studentRoleId,
                'model_type' => User::class,
                'model_id' => $userId,
            ])
            ->all();

        foreach (array_chunk($roleRows, $this->chunk) as $chunk) {
            DB::table('model_has_roles')->insert($chunk);
        }
    }

    /** @return list<int> */
    private function seedClasses(int $count): array
    {
        $this->command?->info("Seeding {$count} classes…");

        $rows = [];
        $titles = [];

        for ($i = 1; $i <= $count; $i++) {
            $title = 'Heavy Class '.$i.' - '.fake()->words(3, true);
            $titles[] = $title;
            $rows[] = [
                'title' => $title,
                'description' => fake()->paragraph(),
                'short_description' => fake()->sentence(),
                'who_is_for' => fake()->sentence(),
                'curriculum' => json_encode([
                    [
                        'title' => 'Module 1',
                        'keypoints' => ['Basics', 'Practice', 'Review'],
                    ],
                ]),
                'is_class_recording' => fake()->boolean(60) ? 1 : 0,
                'price' => fake()->randomElement([2000, 3000, 4000, 5000, 7500]),
                'duration_in_days' => fake()->randomElement([30, 60, 90, 120]),
                'total_classes' => fake()->numberBetween(8, 36),
                'image' => null,
                'is_active' => 1,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            if (count($rows) >= $this->chunk) {
                DB::table('classes')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('classes')->insert($rows);
        }

        return DB::table('classes')->whereIn('title', $titles)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $classIds
     * @param  list<int>  $teacherIds
     * @return list<array{id: int, class_id: int, teacher_id: int, price: float|string}>
     */
    private function seedBatches(array $classIds, array $teacherIds, int $batchesPerClass): array
    {
        $total = count($classIds) * $batchesPerClass;
        $this->command?->info("Seeding {$total} batches…");

        $classPrices = DB::table('classes')->whereIn('id', $classIds)->pluck('price', 'id');
        $rows = [];
        $names = [];
        $teacherCount = count($teacherIds);

        foreach ($classIds as $classIndex => $classId) {
            for ($b = 1; $b <= $batchesPerClass; $b++) {
                $name = "Heavy Batch C{$classId}-{$b}";
                $names[] = $name;
                $start = now()->subDays(fake()->numberBetween(0, 40))->addDays(($classIndex + $b) % 20);
                $duration = fake()->randomElement([30, 60, 90]);
                $status = fake()->randomElement(['upcoming', 'ongoing', 'ongoing', 'completed']);

                $rows[] = [
                    'class_id' => $classId,
                    'teacher_id' => $teacherIds[($classIndex + $b) % $teacherCount],
                    'name' => $name,
                    'total_seat' => 500,
                    'filled_seat' => 0,
                    'start_date' => $start->toDateString(),
                    'end_date' => $start->copy()->addDays($duration)->toDateString(),
                    'zoom_link' => 'https://zoom.us/j/'.fake()->numerify('#########'),
                    'status' => $status,
                    'active_status' => 1,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];

                if (count($rows) >= $this->chunk) {
                    DB::table('batches')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('batches')->insert($rows);
        }

        return DB::table('batches')
            ->whereIn('name', $names)
            ->orderBy('id')
            ->get(['id', 'class_id', 'teacher_id'])
            ->map(fn ($batch) => [
                'id' => (int) $batch->id,
                'class_id' => (int) $batch->class_id,
                'teacher_id' => (int) $batch->teacher_id,
                'price' => $classPrices[$batch->class_id] ?? 3000,
            ])
            ->all();
    }

    /**
     * @param  list<array{id: int, class_id: int, teacher_id: int, price: float|string}>  $batches
     * @param  list<int>  $teacherIds
     */
    private function seedSchedulesAndAvailability(array $batches, array $teacherIds): void
    {
        $this->command?->info('Seeding schedules and teacher availability…');

        $scheduleRows = [];
        foreach ($batches as $batch) {
            foreach ([1, 3, 5] as $day) {
                $scheduleRows[] = [
                    'batch_id' => $batch['id'],
                    'teacher_id' => $batch['teacher_id'],
                    'day_of_week' => $day,
                    'start_time' => '18:00:00',
                    'end_time' => '18:30:00',
                    'reminder_sent_date' => null,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];

                if (count($scheduleRows) >= $this->chunk) {
                    DB::table('batch_schedules')->insert($scheduleRows);
                    $scheduleRows = [];
                }
            }
        }

        if ($scheduleRows !== []) {
            DB::table('batch_schedules')->insert($scheduleRows);
        }

        $availabilityRows = [];
        foreach ($teacherIds as $teacherId) {
            foreach ([0, 1, 2, 3, 4, 5] as $day) {
                $availabilityRows[] = [
                    'teacher_id' => $teacherId,
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '12:00:00',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
                $availabilityRows[] = [
                    'teacher_id' => $teacherId,
                    'day_of_week' => $day,
                    'start_time' => '16:00:00',
                    'end_time' => '21:00:00',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];

                if (count($availabilityRows) >= $this->chunk) {
                    DB::table('teacher_availabilities')->insert($availabilityRows);
                    $availabilityRows = [];
                }
            }
        }

        if ($availabilityRows !== []) {
            DB::table('teacher_availabilities')->insert($availabilityRows);
        }
    }

    /**
     * @param  list<array{id: int, class_id: int, teacher_id: int, price: float|string}>  $batches
     * @param  list<int>  $studentIds
     * @return list<array{batch_id: int, class_id: int, teacher_id: int, user_id: int, enrollment_id: int}>
     */
    private function seedEnrollmentsAndPayments(array $batches, array $studentIds, int $perBatch): array
    {
        $this->command?->info('Seeding enrollments and payments…');

        $studentCount = count($studentIds);
        $perBatch = min($perBatch, $studentCount);
        $pairs = [];
        $enrollmentRows = [];
        $filledByBatch = [];

        foreach ($batches as $batchIndex => $batch) {
            $offset = ($batchIndex * 37) % max(1, $studentCount);
            $selected = [];

            for ($i = 0; $i < $perBatch; $i++) {
                $selected[] = $studentIds[($offset + $i) % $studentCount];
            }

            $selected = array_values(array_unique($selected));

            foreach ($selected as $userId) {
                $enrollmentRows[] = [
                    'user_id' => $userId,
                    'batch_id' => $batch['id'],
                    'class_id' => $batch['class_id'],
                    'status' => 'active',
                    'enrolled_at' => $this->now,
                    'expiry_date' => now()->addDays(90)->toDateTimeString(),
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];

                $filledByBatch[$batch['id']] = ($filledByBatch[$batch['id']] ?? 0) + 1;

                if (count($enrollmentRows) >= $this->chunk) {
                    $pairs = array_merge($pairs, $this->flushEnrollmentsAndPayments($enrollmentRows, $batches));
                    $enrollmentRows = [];
                }
            }
        }

        if ($enrollmentRows !== []) {
            $pairs = array_merge($pairs, $this->flushEnrollmentsAndPayments($enrollmentRows, $batches));
        }

        foreach ($filledByBatch as $batchId => $filled) {
            DB::table('batches')->where('id', $batchId)->update(['filled_seat' => $filled]);
        }

        $this->command?->info('Enrollments created: '.count($pairs));

        return $pairs;
    }

    /**
     * @param  list<array<string, mixed>>  $enrollmentRows
     * @param  list<array{id: int, class_id: int, teacher_id: int, price: float|string}>  $batches
     * @return list<array{batch_id: int, class_id: int, teacher_id: int, user_id: int, enrollment_id: int}>
     */
    private function flushEnrollmentsAndPayments(array $enrollmentRows, array $batches): array
    {
        $batchMap = collect($batches)->keyBy('id');
        $before = (int) DB::table('enrollments')->max('id');

        DB::table('enrollments')->insert($enrollmentRows);

        $created = DB::table('enrollments')
            ->where('id', '>', $before)
            ->orderBy('id')
            ->get(['id', 'user_id', 'batch_id', 'class_id']);

        $pairs = [];
        $paymentRows = [];

        foreach ($created as $enrollment) {
            $batch = $batchMap->get($enrollment->batch_id);
            $pairs[] = [
                'batch_id' => (int) $enrollment->batch_id,
                'class_id' => (int) $enrollment->class_id,
                'teacher_id' => (int) ($batch['teacher_id'] ?? 0),
                'user_id' => (int) $enrollment->user_id,
                'enrollment_id' => (int) $enrollment->id,
            ];

            $status = fake()->randomElement(['paid', 'paid', 'paid', 'pending', 'failed']);
            $paymentId = 'H'.str_pad((string) $this->paymentSeq, 9, '0', STR_PAD_LEFT);
            $this->paymentSeq++;

            $paymentRows[] = [
                'payment_id' => $paymentId,
                'user_id' => $enrollment->user_id,
                'enrollment_id' => $enrollment->id,
                'batch_id' => $enrollment->batch_id,
                'amount' => $batch['price'] ?? 3000,
                'currency' => 'USD',
                'payment_method' => fake()->randomElement(['stripe', 'paypal', 'token']),
                'transaction_id' => 'txn_'.Str::lower(Str::random(16)).'_'.$enrollment->id,
                'status' => $status,
                'paid_at' => $status === 'paid' ? $this->now : null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            if (count($paymentRows) >= $this->chunk) {
                DB::table('payments')->insert($paymentRows);
                $paymentRows = [];
            }
        }

        if ($paymentRows !== []) {
            DB::table('payments')->insert($paymentRows);
        }

        return $pairs;
    }

    /**
     * @param  list<array{batch_id: int, class_id: int, teacher_id: int, user_id: int, enrollment_id: int}>  $pairs
     */
    private function seedAttendances(array $pairs, int $datesCount): void
    {
        $this->command?->info('Seeding attendances…');

        $byBatch = collect($pairs)->groupBy('batch_id');
        $rows = [];
        $total = 0;

        foreach ($byBatch as $batchId => $batchPairs) {
            $students = $batchPairs->take(80)->pluck('user_id')->all();

            for ($d = 0; $d < $datesCount; $d++) {
                $date = now()->subDays($datesCount - $d)->toDateString();

                foreach ($students as $userId) {
                    $rows[] = [
                        'batch_id' => $batchId,
                        'user_id' => $userId,
                        'class_date' => $date,
                        'status' => fake()->boolean(85) ? 'present' : 'absent',
                        'created_at' => $this->now,
                        'updated_at' => $this->now,
                    ];
                    $total++;

                    if (count($rows) >= $this->chunk) {
                        DB::table('attendances')->insert($rows);
                        $rows = [];
                    }
                }
            }
        }

        if ($rows !== []) {
            DB::table('attendances')->insert($rows);
        }

        $this->command?->info("Attendances created: {$total}");
    }

    /**
     * @param  list<array{id: int, class_id: int, teacher_id: int, price: float|string}>  $batches
     * @param  list<int>  $studentIds
     */
    private function seedStudentActivityNotes(array $batches, array $studentIds, int $count): void
    {
        $this->command?->info("Seeding {$count} student activity notes…");

        $statuses = StudentActivityNote::STATUSES;
        $rows = [];
        $batchCount = count($batches);
        $studentCount = count($studentIds);

        for ($i = 0; $i < $count; $i++) {
            $batch = $batches[$i % $batchCount];

            $rows[] = [
                'teacher_id' => $batch['teacher_id'],
                'batch_id' => $batch['id'],
                'student_user_id' => $studentIds[$i % $studentCount],
                'comment' => fake()->sentence(12),
                'status' => $statuses[$i % count($statuses)],
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            if (count($rows) >= $this->chunk) {
                DB::table('student_activity_notes')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('student_activity_notes')->insert($rows);
        }
    }

    /**
     * @param  list<array{id: int, class_id: int, teacher_id: int, price: float|string}>  $batches
     * @param  list<array{batch_id: int, class_id: int, teacher_id: int, user_id: int, enrollment_id: int}>  $pairs
     */
    private function seedAssignmentsAndSubmissions(array $batches, array $pairs, int $perBatch): void
    {
        $this->command?->info('Seeding assignments and submissions…');

        $assignmentRows = [];

        foreach ($batches as $batch) {
            for ($a = 1; $a <= $perBatch; $a++) {
                $starts = now()->subDays(10 + $a);
                $assignmentRows[] = [
                    'batch_id' => $batch['id'],
                    'teacher_id' => $batch['teacher_id'],
                    'title' => "Heavy Assignment {$a} for batch {$batch['id']}",
                    'description' => fake()->paragraph(),
                    'attachment' => null,
                    'starts_at' => $starts->toDateTimeString(),
                    'due_at' => $starts->copy()->addDays(7)->toDateTimeString(),
                    'total_marks' => 100,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];

                if (count($assignmentRows) >= $this->chunk) {
                    DB::table('batch_assignments')->insert($assignmentRows);
                    $assignmentRows = [];
                }
            }
        }

        if ($assignmentRows !== []) {
            DB::table('batch_assignments')->insert($assignmentRows);
        }

        $assignments = DB::table('batch_assignments')
            ->where('title', 'like', 'Heavy Assignment%')
            ->get(['id', 'batch_id']);

        $studentsByBatch = collect($pairs)->groupBy('batch_id');
        $submissionRows = [];
        $total = 0;

        foreach ($assignments as $assignment) {
            $students = $studentsByBatch->get($assignment->batch_id, collect())->take(40);

            foreach ($students as $pair) {
                $submissionRows[] = [
                    'assignment_id' => $assignment->id,
                    'student_user_id' => $pair['user_id'],
                    'file_path' => 'assignments/heavy/'.$assignment->id.'_'.$pair['user_id'].'.pdf',
                    'obtained_marks' => fake()->boolean(70) ? fake()->randomFloat(2, 40, 100) : null,
                    'feedback' => fake()->boolean(50) ? fake()->sentence() : null,
                    'graded_at' => fake()->boolean(60) ? $this->now : null,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
                $total++;

                if (count($submissionRows) >= $this->chunk) {
                    DB::table('assignment_submissions')->insert($submissionRows);
                    $submissionRows = [];
                }
            }
        }

        if ($submissionRows !== []) {
            DB::table('assignment_submissions')->insert($submissionRows);
        }

        $this->command?->info("Assignment submissions created: {$total}");
    }

    /**
     * @param  list<array{id: int, class_id: int, teacher_id: int, price: float|string}>  $batches
     */
    private function seedTeacherNotesAndRecordings(array $batches): void
    {
        $this->command?->info('Seeding teacher notes and class recordings…');

        $teacherUserIds = DB::table('teachers')->pluck('user_id', 'id');
        $noteRows = [];
        $recordingRows = [];

        foreach ($batches as $batch) {
            $userId = $teacherUserIds[$batch['teacher_id']] ?? null;

            if ($userId) {
                for ($n = 1; $n <= 3; $n++) {
                    $noteRows[] = [
                        'title' => "Heavy note {$n} batch {$batch['id']}",
                        'user_id' => $userId,
                        'batch_id' => $batch['id'],
                        'note' => fake()->paragraph(),
                        'note_file' => null,
                        'note_link' => fake()->boolean(40) ? fake()->url() : null,
                        'created_at' => $this->now,
                        'updated_at' => $this->now,
                    ];
                }
            }

            for ($r = 1; $r <= 5; $r++) {
                $recordingRows[] = [
                    'batch_id' => $batch['id'],
                    'class_date' => now()->subDays($r * 2)->toDateString(),
                    'recording_url' => 'https://example.com/recordings/'.$batch['id'].'/'.$r,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }

            if (count($noteRows) >= $this->chunk) {
                DB::table('teacher_notes')->insert($noteRows);
                $noteRows = [];
            }

            if (count($recordingRows) >= $this->chunk) {
                DB::table('class_recordings')->insert($recordingRows);
                $recordingRows = [];
            }
        }

        if ($noteRows !== []) {
            DB::table('teacher_notes')->insert($noteRows);
        }

        if ($recordingRows !== []) {
            DB::table('class_recordings')->insert($recordingRows);
        }
    }

    /**
     * @param  list<array{id: int, class_id: int, teacher_id: int, price: float|string}>  $batches
     * @param  list<int>  $studentIds
     */
    private function seedWaitlists(array $batches, array $studentIds, int $count): void
    {
        $this->command?->info("Seeding {$count} waitlist rows…");

        $rows = [];
        $seen = [];
        $batchCount = count($batches);
        $studentCount = count($studentIds);
        $created = 0;
        $attempts = 0;

        while ($created < $count && $attempts < $count * 3) {
            $attempts++;
            $batchId = $batches[$attempts % $batchCount]['id'];
            $userId = $studentIds[$attempts % $studentCount];
            $key = $userId.'-'.$batchId;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rows[] = [
                'user_id' => $userId,
                'batch_id' => $batchId,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
            $created++;

            if (count($rows) >= $this->chunk) {
                DB::table('waitlists')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('waitlists')->insert($rows);
        }
    }

    /**
     * @param  list<array{id: int, class_id: int, teacher_id: int, price: float|string}>  $batches
     * @param  list<int>  $studentIds
     */
    private function seedNotificationLogs(array $batches, array $studentIds, int $count): void
    {
        $this->command?->info("Seeding {$count} notification logs…");

        $rows = [];
        $batchCount = count($batches);
        $studentCount = count($studentIds);

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'user_id' => $studentIds[$i % $studentCount],
                'batch_id' => $batches[$i % $batchCount]['id'],
                'type' => fake()->randomElement(['email', 'whatsapp']),
                'message_type' => fake()->randomElement(['class_reminder', 'payment', 'general']),
                'message' => fake()->sentence(10),
                'status' => fake()->boolean(90) ? 'sent' : 'failed',
                'sent_at' => $this->now,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            if (count($rows) >= $this->chunk) {
                DB::table('notification_logs')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('notification_logs')->insert($rows);
        }
    }

    /** @param  list<int>  $studentIds */
    private function seedPasswordOtpsAndResets(array $studentIds): void
    {
        $this->command?->info('Seeding password OTPs and reset tokens…');

        $sample = array_slice($studentIds, 0, 500);
        $emails = DB::table('users')->whereIn('id', $sample)->pluck('email', 'id');

        $otpRows = [];
        $resetRows = [];

        foreach ($sample as $userId) {
            $otpRows[] = [
                'user_id' => $userId,
                'otp' => str_pad((string) fake()->numberBetween(0, 999999), 6, '0', STR_PAD_LEFT),
                'expires_at' => now()->addMinutes(15)->toDateTimeString(),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            if ($emails->has($userId)) {
                $resetRows[] = [
                    'email' => $emails[$userId],
                    'token' => Hash::make(Str::random(64)),
                    'created_at' => $this->now,
                ];
            }

            if (count($otpRows) >= $this->chunk) {
                DB::table('password_otps')->insert($otpRows);
                $otpRows = [];
            }

            if (count($resetRows) >= $this->chunk) {
                DB::table('password_reset_tokens')->insert($resetRows);
                $resetRows = [];
            }
        }

        if ($otpRows !== []) {
            DB::table('password_otps')->insert($otpRows);
        }

        if ($resetRows !== []) {
            DB::table('password_reset_tokens')->insert($resetRows);
        }
    }

    private function seedAdminDirectPermissions(): void
    {
        $this->command?->info('Seeding model_has_permissions for admin…');

        $adminId = DB::table('users')->where('email', 'admin@gmail.com')->value('id');

        if (! $adminId) {
            return;
        }

        $permissionIds = DB::table('permissions')->where('guard_name', 'api')->pluck('id');
        $rows = [];

        foreach ($permissionIds as $permissionId) {
            $rows[] = [
                'permission_id' => $permissionId,
                'model_type' => User::class,
                'model_id' => $adminId,
            ];
        }

        if ($rows !== []) {
            DB::table('model_has_permissions')->insertOrIgnore($rows);
        }
    }

    /** @param  list<int>  $studentIds */
    private function seedFrameworkTables(array $studentIds): void
    {
        $this->command?->info('Seeding sessions, jobs, cache, and failed_jobs…');

        $sessionRows = [];
        $sample = array_slice($studentIds, 0, 200);

        foreach ($sample as $index => $userId) {
            $sessionRows[] = [
                'id' => Str::random(40),
                'user_id' => $userId,
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'payload' => base64_encode(serialize(['user_id' => $userId])),
                'last_activity' => now()->subMinutes($index)->timestamp,
            ];

            if (count($sessionRows) >= $this->chunk) {
                DB::table('sessions')->insert($sessionRows);
                $sessionRows = [];
            }
        }

        if ($sessionRows !== []) {
            DB::table('sessions')->insert($sessionRows);
        }

        $jobRows = [];
        for ($i = 1; $i <= 100; $i++) {
            $jobRows[] = [
                'queue' => 'default',
                'payload' => json_encode(['displayName' => 'SeededJob'.$i, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => []]),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ];
        }
        DB::table('jobs')->insert($jobRows);

        DB::table('job_batches')->insert([
            [
                'id' => (string) Str::uuid(),
                'name' => 'heavy-seed-batch',
                'total_jobs' => 10,
                'pending_jobs' => 0,
                'failed_jobs' => 1,
                'failed_job_ids' => json_encode(['seed-failed-1']),
                'options' => json_encode([]),
                'cancelled_at' => null,
                'created_at' => now()->timestamp,
                'finished_at' => now()->timestamp,
            ],
        ]);

        $failedRows = [];
        for ($i = 1; $i <= 50; $i++) {
            $failedRows[] = [
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode(['displayName' => 'FailedSeedJob'.$i]),
                'exception' => 'Seeded failure example #'.$i,
                'failed_at' => $this->now,
            ];
        }
        DB::table('failed_jobs')->insert($failedRows);

        $cacheRows = [];
        for ($i = 1; $i <= 100; $i++) {
            $cacheRows[] = [
                'key' => 'heavy_seed_cache_'.$i,
                'value' => serialize(['index' => $i, 'message' => fake()->sentence()]),
                'expiration' => now()->addDay()->timestamp,
            ];
        }
        DB::table('cache')->insert($cacheRows);

        $lockRows = [];
        for ($i = 1; $i <= 20; $i++) {
            $lockRows[] = [
                'key' => 'heavy_seed_lock_'.$i,
                'owner' => Str::random(16),
                'expiration' => now()->addHour()->timestamp,
            ];
        }
        DB::table('cache_locks')->insert($lockRows);
    }
}
