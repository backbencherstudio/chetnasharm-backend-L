<?php

namespace App\Notifications\Channels;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use App\Models\NotificationLog;

class WhatsAppChannel
{
    public function send($notifiable, $notification)
    {
        if (!method_exists($notification, 'toWhatsapp')) {
            return;
        }

        $data = $notification->toWhatsapp($notifiable);

        if (!$data) {
            return;
        }

        $status = 'sent';
        $errorMessage = null;

        try {

            $twilio = new Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );

            $twilio->messages->create(
                "whatsapp:" . $data['to'],
                [
                    'from' => "whatsapp:" . config('services.twilio.whatsapp_from'),
                    'body' => $data['message'],
                ]
            );

        } catch (\Exception $e) {
            $status = 'failed';
            $errorMessage = $e->getMessage();

            Log::error('WhatsApp failed: ' . $errorMessage);
        }

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'batch_id' => $notification->batch->id ?? null,
            'type' => 'whatsapp',
            'message_type' => 'class_reminder',
            'message' => $data['message'],
            'status' => $status,
            'sent_at' => now(),
        ]);
    }
}
