<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use Carbon\Carbon;
use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\Setting;
use App\Notifications\ClassReminderNotification;
use Illuminate\Support\Facades\Log;

class SendClassReminderJob implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        $minutes = Setting::value('class_notify_time');

        if ($minutes <= 0) {
            $minutes = 20;
        }

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

                    if (!$batch) {
                        continue;
                    }

                    try {
                        $teacherUser = $batch?->teacher?->user;

                        if ($teacherUser) {
                            $teacherUser->notify(
                                new ClassReminderNotification($batch, $schedule)
                            );
                        }
                    } catch (\Exception $e) {
                        Log::error('Teacher notify failed', [
                            'schedule_id' => $schedule->id,
                            'error' => $e->getMessage()
                        ]);
                    }

                    foreach ($batch->enrollments as $enrollment) {

                        $student = $enrollment->user;

                        if (!$student) {
                            continue;
                        }

                        try {
                            $student->notify(
                                new ClassReminderNotification($batch, $schedule)
                            );
                        } catch (\Exception $e) {
                            Log::error('Student notify failed', [
                                'user_id' => $student->id,
                                'schedule_id' => $schedule->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    $schedule->update([
                        'reminder_sent_date' => now()->toDateString(),
                    ]);
                }
            });
    }
}
