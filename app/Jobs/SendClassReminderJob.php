<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use Carbon\Carbon;
use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\Setting;
use App\Notifications\ClassReminderNotification;

class SendClassReminderJob implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        $minutes = Setting::value('class_notify_time');

        $now = Carbon::now();
        $targetTime = $now->copy()->addMinutes($minutes);

        BatchSchedule::with([
                'batch.teacher.user:id,name,email,mobile',
                'batch.enrollments.user:id,name,email,mobile'
            ])
            ->where(function ($query) {
                $query->whereNull('reminder_sent_date')
                      ->orWhereDate('reminder_sent_date', '!=', now()->toDateString());
            })
            ->whereBetween('start_time', [
                $targetTime->copy()->subMinute(),
                $targetTime->copy()->addMinute()
            ])
            ->chunkById(200, function ($schedules) {

                foreach ($schedules as $schedule) {

                    $batch = $schedule->batch;

                    $teacherUser = $batch?->teacher?->user;

                    if ($teacherUser) {
                        $teacherUser->notify(
                            new ClassReminderNotification($batch, $schedule)
                        );
                    }

                    foreach ($batch->enrollments as $enrollment) {

                        $student = $enrollment->user;

                        if ($student) {
                            $student->notify(
                                new ClassReminderNotification($batch, $schedule)
                            );
                        }
                    }

                    $schedule->update([
                        'reminder_sent_date' => now()->toDateString(),
                    ]);
                }
            });
    }
}
