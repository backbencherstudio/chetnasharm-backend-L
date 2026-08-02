<?php

namespace App\Jobs;

use App\Models\BatchSchedule;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Notifications\ClassReminderNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendClassReminderJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the primary class operation.
     *
     * @return void
     */
    public function handle()
    {

        $minutes = Setting::value('class_notify_time');

        if ($minutes <= 0) {
            $minutes = 20;
        }

        $now = now();

        $currentDate = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        $todayWeekDay = $now->dayOfWeek;

        BatchSchedule::with([
            'batch.teacher.user:id,name,email,mobile',
            'batch.enrollments.user:id,name,email,mobile',
        ])

            ->where('day_of_week', $todayWeekDay)

            ->where(function ($query) use ($currentDate) {
                $query->whereNull('reminder_sent_date')
                    ->orWhereDate('reminder_sent_date', '!=', $currentDate);
            })

            ->whereHas('batch', function ($query) use ($currentDate) {
                $query->whereDate('start_date', '<=', $currentDate)
                    ->whereDate('end_date', '>=', $currentDate);
            })

            ->whereRaw(
                'SUBTIME(start_time, SEC_TO_TIME(?)) <= ?',
                [
                    $minutes * 60,
                    $currentTime,
                ]
            )
            ->where('start_time', '>', $currentTime)

            ->chunkById(200, function ($schedules) use ($currentDate) {

                foreach ($schedules as $schedule) {

                    $batch = $schedule->batch;

                    if (! $batch) {
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

                        NotificationLog::create([
                            'user_id' => $teacherUser?->id,
                            'batch_id' => $batch->id,
                            'type' => 'email',
                            'message_type' => 'class_reminder',
                            'message' => "Your class {$batch->name} starts at ".Carbon::parse($schedule->start_time)->format('h:i A'),
                            'status' => 'failed',
                            'sent_at' => now(),
                        ]);

                    }

                    foreach ($batch->enrollments as $enrollment) {

                        $student = $enrollment->user;

                        if (! $student) {
                            continue;
                        }

                        try {

                            $student->notify(
                                new ClassReminderNotification($batch, $schedule)
                            );

                        } catch (\Exception $e) {

                            NotificationLog::create([
                                'user_id' => $student->id,
                                'batch_id' => $batch->id,
                                'type' => 'email',
                                'message_type' => 'class_reminder',
                                'message' => "Your class {$batch->name} starts at ".Carbon::parse($schedule->start_time)->format('h:i A'),
                                'status' => 'failed',
                                'sent_at' => now(),
                            ]);

                        }
                    }

                    $schedule->update([
                        'reminder_sent_date' => $currentDate,
                    ]);
                }
            });
    }
}
