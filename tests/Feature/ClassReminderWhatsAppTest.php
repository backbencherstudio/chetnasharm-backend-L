<?php

use App\Jobs\SendClassReminderJob;
use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\ClassReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

/**
 * @return array{0: User, 1: Batch, 2: BatchSchedule, 3: User}
 */
function createReminderContext(): array
{
    Setting::create([
        'class_time' => 30,
        'class_notify_time' => 30,
        'integrations' => [
            'stripe' => ['key' => null, 'secret' => null, 'webhook_secret' => null],
            'paypal' => ['client_id' => null, 'client_secret' => null, 'mode' => 'sandbox', 'base_url' => null],
            'whatsapp' => [
                'token' => 'wa-test-token',
                'phone_number_id' => '1112996207',
                'url' => 'https://graph.facebook.com/v25.0',
            ],
        ],
    ]);

    $teacherUser = User::factory()->create([
        'name' => 'Teacher One',
        'mobile' => '+8801711111111',
    ]);
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create(['user_id' => $teacherUser->id]);

    $class = ClassModel::create([
        'title' => 'Spoken English',
        'description' => 'Desc',
        'price' => 100,
        'duration_in_days' => 30,
        'total_classes' => 10,
        'is_active' => 1,
    ]);

    $batch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Morning Batch',
        'total_seat' => 10,
        'filled_seat' => 1,
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'ongoing',
        'active_status' => 1,
        'zoom_link' => 'https://zoom.example/join',
    ]);

    $student = User::factory()->create([
        'name' => 'Student Alpha',
        'mobile' => '+8801722222222',
    ]);
    $student->assignRole('student');

    Enrollment::create([
        'user_id' => $student->id,
        'batch_id' => $batch->id,
        'class_id' => $class->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'expiry_date' => $batch->end_date,
    ]);

    $start = now()->addMinutes(20);

    $schedule = BatchSchedule::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'day_of_week' => now()->dayOfWeek,
        'start_time' => $start->format('H:i:s'),
        'end_time' => $start->copy()->addMinutes(30)->format('H:i:s'),
    ]);

    return [$teacherUser, $batch, $schedule, $student];
}

test('whatsapp channel sends meta template payload from notification', function () {
    [, $batch, $schedule, $student] = createReminderContext();

    Mail::fake();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
    ]);

    $student->notifyNow(new ClassReminderNotification($batch, $schedule));

    Http::assertSent(function ($request) use ($student, $batch, $schedule) {
        $data = $request->data();

        return $request->url() === 'https://graph.facebook.com/v25.0/1112996207/messages'
            && $request->hasHeader('Authorization', 'Bearer wa-test-token')
            && $data['to'] === '8801722222222'
            && $data['template']['name'] === 'class_reminder'
            && $data['template']['components'][0]['parameters'][0]['text'] === $student->name
            && $data['template']['components'][0]['parameters'][1]['text'] === $batch->name
            && $data['template']['components'][0]['parameters'][2]['text'] === Carbon::parse($schedule->start_time)->format('h:i A')
            && $data['template']['components'][0]['parameters'][3]['text'] === 'https://zoom.example/join';
    });

    expect(NotificationLog::query()->where('type', 'whatsapp')->where('status', 'sent')->exists())->toBeTrue();
});

test('whatsapp channel logs failure when credentials missing', function () {
    [, $batch, $schedule, $student] = createReminderContext();

    Setting::query()->first()->update([
        'integrations' => [
            'whatsapp' => [
                'token' => null,
                'phone_number_id' => null,
                'url' => null,
            ],
        ],
    ]);

    Mail::fake();
    Http::fake();

    $student->notifyNow(new ClassReminderNotification($batch, $schedule));

    Http::assertNothingSent();

    expect(
        NotificationLog::query()
            ->where('type', 'whatsapp')
            ->where('status', 'failed')
            ->where('message', 'like', '%credentials%')
            ->exists()
    )->toBeTrue();
});

test('reminder job queues notifications for teacher and students', function () {
    [$teacherUser, $batch, $schedule, $student] = createReminderContext();

    Notification::fake();

    (new SendClassReminderJob)->handle();

    Notification::assertSentTo($teacherUser, ClassReminderNotification::class);
    Notification::assertSentTo($student, ClassReminderNotification::class);

    expect($schedule->fresh()->reminder_sent_date)->toBe(now()->toDateString());
});

test('class reminder skips whatsapp channel when mobile missing', function () {
    [, $batch, $schedule, $student] = createReminderContext();
    $student->update(['mobile' => null]);

    $notification = new ClassReminderNotification($batch, $schedule);

    expect($notification->via($student))->toBe(['mail'])
        ->and($notification->toWhatsapp($student))->toBeNull();
});
