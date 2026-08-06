<?php

use App\Common\Pagination;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

test('pagination helper caps per page', function () {
    $request = Request::create('/', 'GET', ['per_page' => 999]);

    expect(Pagination::perPage($request))->toBe(50)
        ->and(Pagination::perPage(Request::create('/', 'GET')))->toBe(10)
        ->and(Pagination::perPage(Request::create('/', 'GET', ['limit' => 25])))->toBe(25);
});

test('otp endpoints are throttled', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'otp@example.com',
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/send-otp', ['email' => $user->email])
            ->assertOk();
    }

    $this->postJson('/api/send-otp', ['email' => $user->email])
        ->assertStatus(429);
});

test('enroll student from payment creates enrollment and increments seats', function () {
    Notification::fake();

    [$batch, $student] = createBatchWithStudent();

    $payment = Payment::create([
        'payment_id' => '123456',
        'user_id' => $student->id,
        'batch_id' => $batch->id,
        'amount' => 100,
        'currency' => 'USD',
        'payment_method' => 'token',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $enrollment = app(EnrollmentService::class)->enrollFromPayment($payment, $batch->id);

    expect($enrollment)->not->toBeNull()
        ->and($batch->fresh()->filled_seat)->toBe(1)
        ->and($payment->fresh()->enrollment_id)->toBe($enrollment->id);

    $this->assertDatabaseHas('enrollments', [
        'user_id' => $student->id,
        'batch_id' => $batch->id,
        'status' => 'active',
    ]);
});

test('change batch updates seats atomically', function () {
    [$fromBatch, $student] = createBatchWithStudent(filledSeat: 1);
    $toBatch = Batch::create([
        'class_id' => $fromBatch->class_id,
        'teacher_id' => $fromBatch->teacher_id,
        'name' => 'Target Batch',
        'total_seat' => 10,
        'filled_seat' => 0,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'upcoming',
        'active_status' => 1,
    ]);

    Enrollment::create([
        'user_id' => $student->id,
        'batch_id' => $fromBatch->id,
        'class_id' => $fromBatch->class_id,
        'status' => 'active',
        'enrolled_at' => now(),
        'expiry_date' => $fromBatch->end_date,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/change-batch', [
            'user_id' => $student->id,
            'from_batch_id' => $fromBatch->id,
            'to_batch_id' => $toBatch->id,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Student batch changed successfully',
        ]);

    expect($fromBatch->fresh()->filled_seat)->toBe(0)
        ->and($toBatch->fresh()->filled_seat)->toBe(1);

    $this->assertDatabaseHas('enrollments', [
        'user_id' => $student->id,
        'batch_id' => $toBatch->id,
    ]);
});

test('teacher cannot view another teachers batch', function () {
    [$batch] = createBatchWithStudent();

    $otherTeacherUser = User::factory()->create();
    $otherTeacherUser->assignRole('teacher');
    Teacher::create([
        'user_id' => $otherTeacherUser->id,
    ]);

    $token = auth('api')->login($otherTeacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/teacher/single-batch/{$batch->id}")
        ->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Unauthorized',
        ]);
});

test('student can view enrolled batch', function () {
    [$batch, $student] = createBatchWithStudent();

    Enrollment::create([
        'user_id' => $student->id,
        'batch_id' => $batch->id,
        'class_id' => $batch->class_id,
        'status' => 'active',
        'enrolled_at' => now(),
        'expiry_date' => $batch->end_date,
    ]);

    $token = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/student/single-batch/{$batch->id}")
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Batch details fetched successfully',
        ]);
});

test('suspended user cannot refresh token', function () {
    $user = User::factory()->create([
        'suspend_status' => 1,
        'password' => bcrypt('password'),
    ]);
    $user->assignRole('student');

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/refresh')
        ->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Your account has been suspended. Please contact admin.',
        ]);
});

test('mark as paid is idempotent when already paid', function () {
    Notification::fake();

    [$batch, $student] = createBatchWithStudent();

    $payment = Payment::create([
        'payment_id' => '654321',
        'user_id' => $student->id,
        'batch_id' => $batch->id,
        'amount' => 50,
        'currency' => 'USD',
        'payment_method' => 'token',
        'status' => 'paid',
        'transaction_id' => 'txn-existing',
        'paid_at' => now(),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/admin/mark-as-paid/{$payment->id}", [
            'transaction_id' => 'txn-new',
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Transaction & enrollment successful',
        ]);

    $this->assertDatabaseHas('enrollments', [
        'user_id' => $student->id,
        'batch_id' => $batch->id,
    ]);

    expect($payment->fresh()->enrollment_id)->not->toBeNull();
});

test('enroll rejects batch mismatch', function () {
    Notification::fake();

    [$batch, $student] = createBatchWithStudent();
    $otherBatch = Batch::create([
        'class_id' => $batch->class_id,
        'teacher_id' => $batch->teacher_id,
        'name' => 'Other Batch',
        'total_seat' => 10,
        'filled_seat' => 0,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'upcoming',
        'active_status' => 1,
    ]);

    $payment = Payment::create([
        'payment_id' => '111222',
        'user_id' => $student->id,
        'batch_id' => $batch->id,
        'amount' => 100,
        'currency' => 'USD',
        'payment_method' => 'token',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    expect(fn () => app(EnrollmentService::class)->enrollFromPayment($payment, $otherBatch->id))
        ->toThrow(Exception::class, 'Batch mismatch');
});

test('change batch rejects when already in target', function () {
    [$fromBatch, $student] = createBatchWithStudent(filledSeat: 1);
    $toBatch = Batch::create([
        'class_id' => $fromBatch->class_id,
        'teacher_id' => $fromBatch->teacher_id,
        'name' => 'Target Batch',
        'total_seat' => 10,
        'filled_seat' => 1,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'upcoming',
        'active_status' => 1,
    ]);

    Enrollment::create([
        'user_id' => $student->id,
        'batch_id' => $fromBatch->id,
        'class_id' => $fromBatch->class_id,
        'status' => 'active',
        'enrolled_at' => now(),
        'expiry_date' => $fromBatch->end_date,
    ]);

    Enrollment::create([
        'user_id' => $student->id,
        'batch_id' => $toBatch->id,
        'class_id' => $toBatch->class_id,
        'status' => 'active',
        'enrolled_at' => now(),
        'expiry_date' => $toBatch->end_date,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/change-batch', [
            'user_id' => $student->id,
            'from_batch_id' => $fromBatch->id,
            'to_batch_id' => $toBatch->id,
        ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Student already enrolled in target batch',
        ]);
});

test('teacher cannot fetch other teacher enrollments', function () {
    [$batch] = createBatchWithStudent();

    $otherTeacherUser = User::factory()->create();
    $otherTeacherUser->assignRole('teacher');
    Teacher::create([
        'user_id' => $otherTeacherUser->id,
    ]);

    $token = auth('api')->login($otherTeacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/enrollments/{$batch->id}")
        ->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Unauthorized',
        ]);
});

test('batch enrollments include user image fields', function () {
    [$batch, $student] = createBatchWithStudent();
    $student->update(['image' => 'users/avatar.webp']);

    $teacherUser = User::query()->find(
        Teacher::query()->where('id', $batch->teacher_id)->value('user_id')
    );

    Enrollment::create([
        'user_id' => $student->id,
        'batch_id' => $batch->id,
        'class_id' => $batch->class_id,
        'status' => 'active',
        'enrolled_at' => now(),
        'expiry_date' => $batch->end_date,
    ]);

    $token = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/enrollments/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.user.id', $student->id)
        ->assertJsonPath('data.0.user.image', 'users/avatar.webp')
        ->assertJsonPath('data.0.user.image_url', asset('storage/users/avatar.webp'));
});

test('login is throttled', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $this->postJson('/api/login', [
        'email' => 'missing@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});
