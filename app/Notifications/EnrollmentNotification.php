<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnrollmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Create a new enrollment confirmation notification. */
    public function __construct(protected Enrollment $enrollment)
    {
        $this->enrollment->load('batch.class', 'batch.schedules');
    }

    /** Get the notification delivery channels. */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Build the enrollment confirmation mail message. */
    public function toMail(object $notifiable): MailMessage
    {
        $batch = $this->enrollment->batch;
        $class = $batch->class;

        $days = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];

        $schedules = $batch->schedules->map(function (object $schedule) use ($days): array {
            return [
                'day' => $days[$schedule->day_of_week] ?? 'Unknown',
                'start' => Carbon::parse($schedule->start_time)->format('H:i'),
                'end' => Carbon::parse($schedule->end_time)->format('H:i'),
            ];
        });

        $messageText = "Enrollment confirmation sent for {$class->title} (Batch {$batch->id})";

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'batch_id' => $batch->id,
            'type' => 'email',
            'message_type' => 'enrollment_confirmation',
            'message' => $messageText,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return (new MailMessage)
            ->subject('Class Enrollment Confirmation')
            ->view('emails.enrollment', [
                'user' => $notifiable,
                'class' => $class,
                'batch' => $batch,
                'schedules' => $schedules,
            ]);
    }
}
