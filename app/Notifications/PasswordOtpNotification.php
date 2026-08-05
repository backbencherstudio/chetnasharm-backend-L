<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Create a new password OTP notification. */
    public function __construct(public int|string $otp) {}

    /** Get the notification delivery channels. */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Build the password reset OTP mail message. */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Password Reset OTP')
            ->view('emails.password_otp', [
                'otp' => $this->otp,
            ]);
    }
}
