<?php

namespace App\Jobs;

use App\Models\BatchSchedule;
use App\Models\Setting;
use App\Notifications\ClassReminderNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendClassReminderJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Prevent overlapping reminder scans.
     */
    public int $uniqueFor = 55;

    public function handle(): void
    {
        $minutes = (int) (Setting::value('class_notify_time') ?: 20);

        if ($minutes <= 0) {
            $minutes = 20;
        }

        $now = now();
        $currentDate = $now->toDateString();
        $currentTime = $now->format('H:i:s');
        $windowEndTime = $now->copy()->addMinutes($minutes)->format('H:i:s');
        $todayWeekDay = $now->dayOfWeek;

        BatchSchedule::query()
            ->with([
                'batch.teacher.user:id,name,email,mobile',
                'batch.enrollments.user:id,name,email,mobile',
            ])
            ->where('day_of_week', $todayWeekDay)
            ->where(function (Builder $query) use ($currentDate): void {
                $query->whereNull('reminder_sent_date')
                    ->orWhereDate('reminder_sent_date', '!=', $currentDate);
            })
            ->whereHas('batch', function (Builder $query) use ($currentDate): void {
                $query->whereDate('start_date', '<=', $currentDate)
                    ->whereDate('end_date', '>=', $currentDate);
            })
            ->where('start_time', '>', $currentTime)
            ->where('start_time', '<=', $windowEndTime)
            ->chunkById(200, function (Collection $schedules) use ($currentDate): void {
                foreach ($schedules as $schedule) {
                    $this->sendRemindersForSchedule($schedule, $currentDate);
                }
            });
    }

    private function sendRemindersForSchedule(BatchSchedule $schedule, string $currentDate): void
    {
        $batch = $schedule->batch;

        if (! $batch) {
            return;
        }

        $recipients = collect();

        if ($batch->teacher?->user) {
            $recipients->push($batch->teacher->user);
        }

        foreach ($batch->enrollments as $enrollment) {
            if ($enrollment->user) {
                $recipients->push($enrollment->user);
            }
        }

        $recipients = $recipients->unique('id');

        foreach ($recipients as $user) {
            try {
                $user->notify(new ClassReminderNotification($batch, $schedule));
            } catch (Throwable $e) {
                Log::error('Failed to queue class reminder', [
                    'user_id' => $user->id,
                    'batch_id' => $batch->id,
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Mark after queueing so the minute scheduler does not re-dispatch the same class.
        $schedule->update([
            'reminder_sent_date' => $currentDate,
        ]);
    }
}
