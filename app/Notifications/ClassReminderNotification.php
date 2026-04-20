<?php

namespace App\Notifications;

use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Channels\WhatsAppChannel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ClassReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $batch;
    public $schedule;

    public function __construct($batch, $schedule)
    {
        $this->batch = $batch;
        $this->schedule = $schedule;
    }

    public function via($notifiable)
    {
        return ['mail', WhatsAppChannel::class];
    }

    public function toMail($notifiable)
    {
        $time = Carbon::parse($this->schedule->start_time)->format('h:i A');

        $messageText = "Your class {$this->batch->name} starts at {$time}";

        try {

            return (new MailMessage)
                ->subject('Class Reminder')
                ->line('Your class is starting soon.')
                ->line('Batch: ' . $this->batch->name)
                ->line('Time: ' . $time)
                ->action('Join Class', $this->batch->zoom_link)
                ->withSymfonyMessage(function () use ($notifiable, $messageText) {

                    NotificationLog::create([
                        'user_id' => $notifiable->id,
                        'batch_id' => $this->batch->id,
                        'type' => 'email',
                        'message_type' => 'class_reminder',
                        'message' => $messageText,
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                });

        } catch (\Exception $e) {

            Log::error('Email failed', [
                'user_id' => $notifiable->id,
                'error' => $e->getMessage()
            ]);

            NotificationLog::create([
                'user_id' => $notifiable->id,
                'batch_id' => $this->batch->id,
                'type' => 'email',
                'message_type' => 'class_reminder',
                'message' => $messageText,
                'status' => 'failed',
                'sent_at' => now(),
            ]);

            return null;
        }
    }

    public function toWhatsapp($notifiable)
    {
        if (empty($notifiable->mobile)) {
            return null;
        }

        return [
            'to' => $notifiable->mobile
        ];
    }
}
