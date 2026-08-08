<?php

namespace Database\Seeders;

use App\Models\StudentActivityNote;
use App\Models\User;
use Database\Seeders\Concerns\GeneratesSeedData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/** Bulk-seeds ~1M+ rows for performance testing. Delete before production. */
class HeavyDataSeeder extends Seeder
{
    use GeneratesSeedData;
    use WithoutModelEvents;

    private int $chunk = 2000;

    private string $passwordHash;

    private string $now;

    private int $teacherRoleId;

    private int $studentRoleId;

    private int $paymentSeq = 1;

    private int $enrollmentCount = 0;

    public function run(): void
    {
        $started = microtime(true);

        $this->passwordHash = Hash::make('12345678');
        $this->now = now()->toDateTimeString();
        $this->paymentSeq = 1;
        $this->enrollmentCount = 0;

        $this->teacherRoleId = (int) Role::query()->where('name', 'teacher')->where('guard_name', 'api')->value('id');
        $this->studentRoleId = (int) Role::query()->where('name', 'student')->where('guard_name', 'api')->value('id');

        if ($this->teacherRoleId === 0 || $this->studentRoleId === 0) {
            $this->command?->error('Roles missing. Run RolePermissionSeeder first.');

            return;
        }

        $this->prepareDatabaseForBulkInsert();

        $this->command?->info('HeavyDataSeeder starting (target ≈ 1M enrollments/payments + related rows)…');

        // 500 teachers × 100k students × 200 classes × 5 batches = 1,000 batches
        // 1,000 batches × 1,000 enrollments = 1,000,000 enrollments (+ payments)
        $teacherIds = $this->seedTeachers(500);
        $studentIds = $this->seedStudents(100000);
        $classIds = $this->seedClasses(200);
        $batches = $this->seedBatches($classIds, $teacherIds, 5);
        $this->seedSchedulesAndAvailability($batches, $teacherIds);
        $this->seedEnrollmentsAndPayments($batches, $studentIds, 1000);
        $this->seedAttendances($batches, 50, 20); // 1000 × 50 × 20 = 1,000,000
        $this->seedStudentActivityNotes($batches, $studentIds, 500000);
        $this->seedAssignmentsAndSubmissions($batches, 5);
        $this->seedTeacherNotesAndRecordings($batches);
        $this->seedWaitlists($batches, $studentIds, 100000);
        $this->seedNotificationLogs($batches, $studentIds, 200000);
        $this->seedPasswordOtpsAndResets($studentIds);
        $this->seedAdminDirectPermissions();
        $this->seedFrameworkTables($studentIds);

        $this->restoreDatabaseAfterBulkInsert();

        $seconds = round(microtime(true) - $started, 1);
        $this->command?->info("HeavyDataSeeder finished in {$seconds}s. Enrollments: {$this->enrollmentCount}");
    }

    private function prepareDatabaseForBulkInsert(): void
    {
        DB::connection()->disableQueryLog();

        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('SET UNIQUE_CHECKS=0');

            try {
                DB::statement('SET SESSION sql_log_bin=0');
            } catch (\Throwable) {
                // Optional; may require elevated MySQL privileges.
            }
        }
    }

    private function restoreDatabaseAfterBulkInsert(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET UNIQUE_CHECKS=1');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
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
                'name' => $this->seedName(),
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
                'country' => $this->seedCountry(),
                'timezone' => $this->seedTimezone(),
                'qualification' => $this->seedPick(['BA English', 'MA Linguistics', 'CELTA', 'TESOL']),
                'expertise' => $this->seedPick(['Spoken English', 'IELTS', 'Business English', 'Grammar']),
                'years_of_exp' => $this->seedNumber(1, 15),
                'bio' => $this->seedSentence(12),
                'about' => $this->seedParagraph(),
                'specializations' => json_encode(['Speaking', 'Writing']),
                'languages_spoken' => json_encode(['English', 'Bengali']),
                'courses_can_teach' => json_encode(['Spoken English']),
                'interests' => json_encode(['Teaching', 'Travel']),
                'is_top' => $this->seedBool(15) ? 1 : 0,
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
                'name' => $this->seedName(),
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
            $title = 'Heavy Class '.$i.' - '.$this->seedWords(3);
            $titles[] = $title;
            $rows[] = [
                'title' => $title,
                'description' => $this->seedParagraph(),
                'short_description' => $this->seedSentence(),
                'who_is_for' => $this->seedSentence(),
                'curriculum' => json_encode([
                    [
                        'title' => 'Module 1',
                        'keypoints' => ['Basics', 'Practice', 'Review'],
                    ],
                ]),
                'is_class_recording' => $this->seedBool(60) ? 1 : 0,
                'price' => $this->seedPick([2000, 3000, 4000, 5000, 7500]),
                'duration_in_days' => $this->seedPick([30, 60, 90, 120]),
                'total_classes' => $this->seedNumber(8, 36),
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
                $start = now()->subDays($this->seedNumber(0, 40))->addDays(($classIndex + $b) % 20);
                $duration = $this->seedPick([30, 60, 90]);
                $status = $this->seedPick(['upcoming', 'ongoing', 'ongoing', 'completed']);

                $rows[] = [
                    'class_id' => $classId,
                    'teacher_id' => $teacherIds[($classIndex + $b) % $teacherCount],
                    'name' => $name,
                    'total_seat' => 1000,
                    'filled_seat' => 0,
                    'start_date' => $start->toDateString(),
                    'end_date' => $start->copy()->addDays($duration)->toDateString(),
                    'zoom_link' => 'https://zoom.us/j/'.$this->seedDigits(9),
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
     */
    private function seedEnrollmentsAndPayments(array $batches, array $studentIds, int $perBatch): void
    {
        $this->command?->info('Seeding enrollments and payments (≈1M)…');

        $studentCount = count($studentIds);
        $perBatch = min($perBatch, $studentCount);
        $batchMap = collect($batches)->keyBy('id');
        $enrollmentRows = [];
        $filledByBatch = [];
        $batchTotal = count($batches);

        foreach ($batches as $batchIndex => $batch) {
            if ($batchIndex > 0 && $batchIndex % 50 === 0) {
                $this->command?->info("  enrollments progress: {$batchIndex}/{$batchTotal} batches ({$this->enrollmentCount} rows)");
            }

            $offset = ($batchIndex * 97) % max(1, $studentCount);
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
                    $this->flushEnrollmentsAndPayments($enrollmentRows, $batchMap);
                    $enrollmentRows = [];
                }
            }
        }

        if ($enrollmentRows !== []) {
            $this->flushEnrollmentsAndPayments($enrollmentRows, $batchMap);
        }

        foreach ($filledByBatch as $batchId => $filled) {
            DB::table('batches')->where('id', $batchId)->update([
                'filled_seat' => $filled,
                'total_seat' => max(1000, $filled),
            ]);
        }

        $this->command?->info("Enrollments created: {$this->enrollmentCount}");
    }

    /**
     * @param  list<array<string, mixed>>  $enrollmentRows
     * @param  Collection<int|string, array{id: int, class_id: int, teacher_id: int, price: float|string}>  $batchMap
     */
    private function flushEnrollmentsAndPayments(array $enrollmentRows, $batchMap): void
    {
        $before = (int) (DB::table('enrollments')->max('id') ?? 0);

        DB::table('enrollments')->insert($enrollmentRows);

        $paymentRows = [];
        $count = count($enrollmentRows);

        for ($i = 0; $i < $count; $i++) {
            $enrollment = $enrollmentRows[$i];
            $enrollmentId = $before + $i + 1;
            $batch = $batchMap->get($enrollment['batch_id']);
            $status = $this->seedPick(['paid', 'paid', 'paid', 'pending', 'failed']);
            $paymentId = (string) Str::uuid();
            $this->paymentSeq++;

            $paymentRows[] = [
                'payment_id' => $paymentId,
                'user_id' => $enrollment['user_id'],
                'enrollment_id' => $enrollmentId,
                'batch_id' => $enrollment['batch_id'],
                'amount' => $batch['price'] ?? 3000,
                'currency' => 'USD',
                'payment_method' => $this->seedPick(['stripe', 'paypal', 'token']),
                'transaction_id' => 'txn_'.$enrollmentId.'_'.$this->paymentSeq,
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

        $this->enrollmentCount += $count;
    }

    /**
     * @param  list<array{id: int, class_id: int, teacher_id: int, price: float|string}>  $batches
     */
    private function seedAttendances(array $batches, int $studentsPerBatch, int $datesCount): void
    {
        $this->command?->info('Seeding attendances (≈1M)…');

        $rows = [];
        $total = 0;
        $batchTotal = count($batches);

        foreach ($batches as $batchIndex => $batch) {
            if ($batchIndex > 0 && $batchIndex % 50 === 0) {
                $this->command?->info("  attendances progress: {$batchIndex}/{$batchTotal} batches ({$total} rows)");
            }

            $studentIds = DB::table('enrollments')
                ->where('batch_id', $batch['id'])
                ->orderBy('id')
                ->limit($studentsPerBatch)
                ->pluck('user_id')
                ->all();

            for ($d = 0; $d < $datesCount; $d++) {
                $date = now()->subDays($datesCount - $d)->toDateString();

                foreach ($studentIds as $userId) {
                    $rows[] = [
                        'batch_id' => $batch['id'],
                        'user_id' => $userId,
                        'class_date' => $date,
                        'status' => $this->seedBool(85) ? 'present' : 'absent',
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
                'comment' => $this->seedSentence(12),
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
     */
    private function seedAssignmentsAndSubmissions(array $batches, int $perBatch): void
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
                    'description' => $this->seedParagraph(),
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

        $submissionRows = [];
        $total = 0;

        foreach ($assignments as $assignment) {
            $studentIds = DB::table('enrollments')
                ->where('batch_id', $assignment->batch_id)
                ->orderBy('id')
                ->limit(40)
                ->pluck('user_id');

            foreach ($studentIds as $userId) {
                $submissionRows[] = [
                    'assignment_id' => $assignment->id,
                    'student_user_id' => $userId,
                    'file_path' => 'assignments/heavy/'.$assignment->id.'_'.$userId.'.pdf',
                    'obtained_marks' => $this->seedBool(70) ? $this->seedFloat(40, 100) : null,
                    'feedback' => $this->seedBool(50) ? $this->seedSentence() : null,
                    'graded_at' => $this->seedBool(60) ? $this->now : null,
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
                        'note' => $this->seedParagraph(),
                        'note_file' => null,
                        'note_link' => $this->seedBool(40) ? $this->seedUrl() : null,
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
                'type' => $this->seedPick(['email', 'whatsapp']),
                'message_type' => $this->seedPick(['class_reminder', 'payment', 'general']),
                'message' => $this->seedSentence(10),
                'status' => $this->seedBool(90) ? 'sent' : 'failed',
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
                'otp' => str_pad((string) $this->seedNumber(0, 999999), 6, '0', STR_PAD_LEFT),
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
                'ip_address' => $this->seedIpv4(),
                'user_agent' => $this->seedUserAgent(),
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
                'value' => serialize(['index' => $i, 'message' => $this->seedSentence()]),
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
