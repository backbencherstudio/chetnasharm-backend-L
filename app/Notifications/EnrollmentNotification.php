<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class EnrollmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $enrollment;

    public function __construct($enrollment)
    {
        $this->enrollment = $enrollment->load('batch.class', 'batch.schedules');
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

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
                'start' => \Carbon\Carbon::parse($schedule->start_time)->format('H:i'),
                'end' => \Carbon\Carbon::parse($schedule->end_time)->format('H:i'),
            ];
        });

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
