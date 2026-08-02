<?php

namespace Tests\Feature;

use App\Actions\EnrollStudentFromPayment;
use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Teacher;
use App\Models\User;
use App\Support\Pagination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'teacher', 'student'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'api']);
        }
    }

    public function test_pagination_helper_caps_per_page(): void
    {
        $request = Request::create('/', 'GET', ['per_page' => 999]);

        $this->assertSame(50, Pagination::perPage($request));
        $this->assertSame(10, Pagination::perPage(Request::create('/', 'GET')));
        $this->assertSame(25, Pagination::perPage(Request::create('/', 'GET', ['limit' => 25])));
    }

    public function test_otp_endpoints_are_throttled(): void
    {
        $user = User::factory()->create([
            'email' => 'otp@example.com',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/send-otp', ['email' => $user->email])
                ->assertOk();
        }

        $this->postJson('/api/send-otp', ['email' => $user->email])
            ->assertStatus(429);
    }

    public function test_enroll_student_from_payment_creates_enrollment_and_increments_seats(): void
    {
        Notification::fake();

        [$batch, $student] = $this->createBatchWithStudent();

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

        $enrollment = app(EnrollStudentFromPayment::class)->handle($payment, $batch->id);

        $this->assertNotNull($enrollment);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $this->assertSame(1, $batch->fresh()->filled_seat);
        $this->assertSame($enrollment->id, $payment->fresh()->enrollment_id);
    }

    public function test_change_batch_updates_seats_atomically(): void
    {
        [$fromBatch, $student] = $this->createBatchWithStudent(filledSeat: 1);
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

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/change-batch', [
                'user_id' => $student->id,
                'from_batch_id' => $fromBatch->id,
                'to_batch_id' => $toBatch->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Student batch changed successfully',
            ]);

        $this->assertSame(0, $fromBatch->fresh()->filled_seat);
        $this->assertSame(1, $toBatch->fresh()->filled_seat);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'batch_id' => $toBatch->id,
        ]);
    }

    public function test_teacher_cannot_view_another_teachers_batch(): void
    {
        [$batch] = $this->createBatchWithStudent();

        $otherTeacherUser = User::factory()->create();
        $otherTeacherUser->assignRole('teacher');
        Teacher::create([
            'name' => 'Other Teacher',
            'email' => $otherTeacherUser->email,
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
    }

    public function test_student_can_view_enrolled_batch(): void
    {
        [$batch, $student] = $this->createBatchWithStudent();

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
    }

    public function test_suspended_user_cannot_refresh_token(): void
    {
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
    }

    public function test_mark_as_paid_is_idempotent_when_already_paid(): void
    {
        Notification::fake();

        [$batch, $student] = $this->createBatchWithStudent();

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
        $this->assertNotNull($payment->fresh()->enrollment_id);
    }

    public function test_enroll_rejects_batch_mismatch(): void
    {
        Notification::fake();

        [$batch, $student] = $this->createBatchWithStudent();
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

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Batch mismatch');

        app(EnrollStudentFromPayment::class)->handle($payment, $otherBatch->id);
    }

    public function test_change_batch_rejects_when_already_in_target(): void
    {
        [$fromBatch, $student] = $this->createBatchWithStudent(filledSeat: 1);
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
    }

    public function test_teacher_cannot_fetch_other_teacher_enrollments(): void
    {
        [$batch] = $this->createBatchWithStudent();

        $otherTeacherUser = User::factory()->create();
        $otherTeacherUser->assignRole('teacher');
        Teacher::create([
            'name' => 'Other Teacher',
            'email' => $otherTeacherUser->email,
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
    }

    public function test_login_is_throttled(): void
    {
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
    }

    /**
     * @return array{0: Batch, 1: User}
     */
    private function createBatchWithStudent(int $filledSeat = 0): array
    {
        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');

        $teacher = Teacher::create([
            'name' => 'Teacher One',
            'email' => $teacherUser->email,
            'user_id' => $teacherUser->id,
        ]);

        $class = ClassModel::create([
            'title' => 'Test Class',
            'description' => 'Desc',
            'price' => 100,
            'duration_in_days' => 30,
            'total_classes' => 10,
            'is_active' => 1,
        ]);

        $batch = Batch::create([
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'name' => 'Batch A',
            'total_seat' => 10,
            'filled_seat' => $filledSeat,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'status' => 'upcoming',
            'active_status' => 1,
            'zoom_link' => 'https://zoom.example/test',
        ]);

        $student = User::factory()->create();
        $student->assignRole('student');

        return [$batch, $student];
    }
}
