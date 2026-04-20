<?php

namespace App\Notifications\Channels;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class WhatsAppChannel
{
    public function send($notifiable, $notification)
    {
        if (!method_exists($notification, 'toWhatsapp')) {
            return;
        }

        $data = $notification->toWhatsapp($notifiable);

        if (!$data || empty($data['to'])) {
            return;
        }

        $phone = ltrim($data['to'], '+');

        $status = 'sent';
        $errorMessage = null;

        try {

            $response = Http::withToken(config('services.whatsapp.token'))
                ->timeout(10)
                ->retry(2, 200)
                ->post(
                    config('services.whatsapp.url') . '/' . config('services.whatsapp.phone_number_id') . '/messages',
                    [
                        'messaging_product' => 'whatsapp',
                        'to' => $phone,
                        'type' => 'template',
                        'template' => [
                            'name' => 'class_reminder',
                            'language' => [
                                'code' => 'en'
                            ],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        ['type' => 'text', 'text' => $notifiable->name ?? 'Student'],
                                        ['type' => 'text', 'text' => $notification->batch->name ?? 'Class'],
                                        ['type' => 'text', 'text' => isset($notification->schedule)
                                            ? Carbon::parse($notification->schedule->start_time)->format('h:i A')
                                            : ''
                                        ],
                                        ['type' => 'text', 'text' => $notification->batch->zoom_link ?? '']
                                    ]
                                ]
                            ]
                        ]
                    ]
                );

            Log::info('Meta response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            $body = $response->json();

            if ($response->failed() || isset($body['error'])) {
                $status = 'failed';
                $errorMessage = $body['error']['message'] ?? 'Unknown error';

                Log::error('Meta WhatsApp failed', $body);
            }

        } catch (\Exception $e) {
            $status = 'failed';
            $errorMessage = $e->getMessage();

            Log::error('Meta WhatsApp exception', [
                'error' => $errorMessage
            ]);
        }

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'batch_id' => $notification->batch->id ?? null,
            'type' => 'whatsapp',
            'message_type' => 'class_reminder',
            'message' => "Reminder sent",
            'status' => $status,
            'sent_at' => now(),
        ]);
    }
}

