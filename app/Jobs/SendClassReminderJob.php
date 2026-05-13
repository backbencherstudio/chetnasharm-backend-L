<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use Carbon\Carbon;
use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Notifications\ClassReminderNotification;
use Illuminate\Support\Facades\Log;

class SendClassReminderJob implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        // Log::info('Class reminder job started');

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
                'batch.enrollments.user:id,name,email,mobile'
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
                "SUBTIME(start_time, SEC_TO_TIME(?)) <= ?",
                [
                    $minutes * 60,
                    $currentTime
                ]
            )
            ->where('start_time', '>', $currentTime)

            ->chunkById(200, function ($schedules) use ($currentDate) {

                foreach ($schedules as $schedule) {

                    $batch = $schedule->batch;

                    if (!$batch) {
                        continue;
                    }

                    // Log::info('Sending reminder', [
                    //     'schedule_id' => $schedule->id,
                    //     'batch_id' => $batch->id
                    // ]);

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
                            'message' => "Your class {$batch->name} starts at " . Carbon::parse($schedule->start_time)->format('h:i A'),
                            'status' => 'failed',
                            'sent_at' => now(),
                        ]);

                        // Log::error('Teacher notify failed', [
                        //     'schedule_id' => $schedule->id,
                        //     'error' => $e->getMessage()
                        // ]);
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

                            NotificationLog::create([
                                'user_id' => $student->id,
                                'batch_id' => $batch->id,
                                'type' => 'email',
                                'message_type' => 'class_reminder',
                                'message' => "Your class {$batch->name} starts at " . Carbon::parse($schedule->start_time)->format('h:i A'),
                                'status' => 'failed',
                                'sent_at' => now(),
                            ]);

                            // Log::error('Student notify failed', [
                            //     'user_id' => $student->id,
                            //     'schedule_id' => $schedule->id,
                            //     'error' => $e->getMessage()
                            // ]);
                        }
                    }

                    $schedule->update([
                        'reminder_sent_date' => $currentDate
                    ]);
                }
            });
    }

}
