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

        $schedules = BatchSchedule::with([
                'batch.students',
                'batch.teacher.user'
            ])
            ->whereBetween('start_time', [
                $targetTime->copy()->subMinute(),
                $targetTime->copy()->addMinute()
            ])
            ->get();

        foreach ($schedules as $schedule) {

            $batch = $schedule->batch;

            if ($batch->teacher?->user) {
                $batch->teacher->user->notify(
                    new ClassReminderNotification($batch, $schedule)
                );
            }

            foreach ($batch->students as $student) {
                $student->notify(
                    new ClassReminderNotification($batch, $schedule)
                );
            }

            $schedule->update(['reminder_sent' => true]);
        }
    }
}
