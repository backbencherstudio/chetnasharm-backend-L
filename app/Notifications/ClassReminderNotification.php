<?php

namespace App\Notifications;

use App\Common\PhoneNormalizer;
use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\NotificationLog;
use App\Notifications\Channels\WhatsAppChannel;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Create a new class reminder notification. */
    public function __construct(
        public Batch $batch,
        public BatchSchedule $schedule,
    ) {}

    /** Get the notification delivery channels. */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (filled($notifiable->mobile ?? null)) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    /** Build the class reminder mail message. */
    public function toMail(object $notifiable): ?MailMessage
    {
        $time = $this->startTime();
        $messageText = $this->reminderText();

        try {
            return (new MailMessage)
                ->subject('Class Reminder')
                ->line('Your class is starting soon.')
                ->line('Batch: '.$this->batch->name)
                ->line('Time: '.$time)
                ->action('Join Class', $this->batch->zoom_link ?? config('app.frontend_url'))
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
        } catch (Throwable $e) {
            Log::error('Class reminder email failed', [
                'user_id' => $notifiable->id,
                'batch_id' => $this->batch->id,
                'error' => $e->getMessage(),
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

    /** Build the WhatsApp template payload for this reminder. */
    public function toWhatsapp(object $notifiable): ?array
    {
        $to = $this->normalizedMobile($notifiable->mobile ?? null);

        if ($to === null) {
            return null;
        }

        $time = $this->startTime();

        return [
            'to' => $to,
            'batch_id' => $this->batch->id,
            'message_type' => 'class_reminder',
            'message' => $this->reminderText(),
            'template' => [
                'name' => 'class_reminder',
                'language' => 'en',
                'body_parameters' => [
                    $notifiable->name ?? 'Student',
                    $this->batch->name ?? 'Class',
                    $time,
                    $this->batch->zoom_link ?? '',
                ],
            ],
        ];
    }

    /** Format the scheduled class start time for display. */
    private function startTime(): string
    {
        return Carbon::parse($this->schedule->start_time)->format('h:i A');
    }

    /** Build the human-readable reminder message text. */
    private function reminderText(): string
    {
        return "Your class {$this->batch->name} starts at {$this->startTime()}";
    }

    /** Normalize a mobile number to E.164 digits without the plus sign. */
    private function normalizedMobile(?string $mobile): ?string
    {
        if (blank($mobile)) {
            return null;
        }

        try {
            return ltrim(PhoneNormalizer::toE164($mobile), '+');
        } catch (Throwable) {
            return null;
        }
    }
}
