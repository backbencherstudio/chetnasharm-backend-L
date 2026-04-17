<?php

namespace App\Notifications\Channels;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

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
        Log::info('Sending WhatsApp to ' . $data['to'] . ': ' . $data['message']);
        $twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );

        try {
            $twilio->messages->create(
                "whatsapp:" . $data['to'],
                [
                    'from' => "whatsapp:" . config('services.twilio.whatsapp_from'),
                    'body' => $data['message'],
                ]
            );
        } catch (\Exception $e) {
            Log::error('WhatsApp failed: ' . $e->getMessage());
        }
    }
}
