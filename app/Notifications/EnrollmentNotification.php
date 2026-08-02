<?php

namespace App\Notifications;

use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnrollmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $enrollment;

    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct($enrollment)
    {
        $this->enrollment = $enrollment->load('batch.class', 'batch.schedules');
    }

    /**
     * Get the notification delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the enrollment confirmation mail message.
     *
     * @return MailMessage
     */
    public function toMail($notifiable)
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

        $schedules = $batch->schedules->map(function ($schedule) use ($days) {
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
