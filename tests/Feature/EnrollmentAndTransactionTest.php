<?php

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

function setupEnrollmentAndTransactionContext(): array
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('teacher');
    $teacher = Teacher::create(['user_id' => $teacherUser->id]);

    $class = ClassModel::create([
        'title' => 'Enrollment Class',
        'description' => 'Desc',
        'price' => 100,
        'duration_in_days' => 30,
        'total_classes' => 10,
        'is_active' => 1,
    ]);

    $batch1 = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Batch 1',
        'total_seat' => 10,
        'filled_seat' => 1,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'ongoing',
        'active_status' => 1,
    ]);

    $batch2 = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Batch 2',
        'total_seat' => 10,
        'filled_seat' => 0,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'ongoing',
        'active_status' => 1,
    ]);

    $student = User::factory()->create(['name' => 'Student One']);
    $student->assignRole('student');

    Enrollment::create([
        'user_id' => $student->id,
        'batch_id' => $batch1->id,
        'class_id' => $class->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    return [$admin, $teacherUser, $teacher, $class, $batch1, $batch2, $student];
}

test('authorized users can view batch enrollments and admin can change student batch', function (): void {
    [$admin, $teacherUser, $teacher, $class, $batch1, $batch2, $student] = setupEnrollmentAndTransactionContext();

    $adminToken = auth('api')->login($admin);

    // List enrollments
    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/api/enrollments/{$batch1->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    // Change batch
    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->postJson('/api/admin/change-batch', [
            'user_id' => $student->id,
            'from_batch_id' => $batch1->id,
            'to_batch_id' => $batch2->id,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Student batch changed successfully');

    expect(Enrollment::where('user_id', $student->id)->where('batch_id', $batch2->id)->exists())->toBeTrue();
});

test('payments listing scopes to authenticated student and admin can mark as paid', function (): void {
    [$admin, $teacherUser, $teacher, $class, $batch1, $batch2, $student] = setupEnrollmentAndTransactionContext();

    $student2 = User::factory()->create(['name' => 'Student Two']);
    $student2->assignRole('student');

    $payment = Payment::create([
        'user_id' => $student2->id,
        'batch_id' => $batch2->id,
        'class_id' => $class->id,
        'payment_id' => 'PAY-12345',
        'payment_method' => 'offline',
        'amount' => 100,
        'status' => 'pending',
    ]);

    // Student 1 checks payments (should be empty for them)
    $student1Token = auth('api')->login($student);
    $res1 = $this->withHeader('Authorization', "Bearer {$student1Token}")
        ->getJson('/api/payments')
        ->assertOk()
        ->assertJsonPath('success', true);
    expect($res1->json('data'))->toBeEmpty();

    // Admin checks payments (should see payment)
    $adminToken = auth('api')->login($admin);
    $resAdmin = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/api/payments')
        ->assertOk()
        ->assertJsonPath('success', true);
    expect($resAdmin->json('data'))->toHaveCount(1);

    // Admin marks as paid
    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->postJson("/api/admin/mark-as-paid/{$payment->id}", [
            'transaction_id' => 'TXN-998877',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($payment->fresh()->status)->toBe('paid');
    expect(Enrollment::where('user_id', $student2->id)->where('batch_id', $batch2->id)->exists())->toBeTrue();
});

test('public and admin settings endpoints return proper responses', function (): void {
    Setting::create([
        'class_time' => 45,
        'support_email' => 'support@example.com',
        'support_number' => '+123456789',
    ]);

    // Public support
    $this->getJson('/api/support')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.support_email', 'support@example.com');

    // Public social links
    $this->getJson('/api/social-links')
        ->assertOk()
        ->assertJsonPath('success', true);

    // Admin updates settings and views class-time
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/class-time')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.class_time', 45);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/settings', [
            'class_time' => 50,
            'class_notify_time' => 15,
            'support_email' => 'newsupport@example.com',
            'support_number' => '+987654321',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Setting::first()->class_time)->toBe(50);
});
