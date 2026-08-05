<?php

namespace App\Notifications\Channels;

use App\Common\IntegrationConfig;
use App\Models\NotificationLog;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    public function __construct(private IntegrationConfig $integrationConfig) {}

    /**
     * Send a WhatsApp Cloud API template message.
     *
     * Expected payload from toWhatsapp():
     * - to: E.164 digits (no +)
     * - message: human-readable log text
     * - batch_id: optional
     * - template.name / template.language / template.body_parameters
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsapp')) {
            return;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $notification->toWhatsapp($notifiable);

        if (! is_array($payload) || blank($payload['to'] ?? null)) {
            return;
        }

        $whatsapp = $this->integrationConfig->whatsapp();

        if (blank($whatsapp['token']) || blank($whatsapp['phone_number_id']) || blank($whatsapp['url'])) {
            $this->log($notifiable, $payload, 'failed', 'WhatsApp credentials are not configured');

            return;
        }

        $template = $payload['template'] ?? [];
        $status = 'sent';
        $errorMessage = null;

        try {
            $response = Http::withToken($whatsapp['token'])
                ->timeout(10)
                ->retry(2, 200)
                ->post(
                    rtrim((string) $whatsapp['url'], '/').'/'.$whatsapp['phone_number_id'].'/messages',
                    [
                        'messaging_product' => 'whatsapp',
                        'to' => $payload['to'],
                        'type' => 'template',
                        'template' => [
                            'name' => $template['name'] ?? 'class_reminder',
                            'language' => [
                                'code' => $template['language'] ?? 'en',
                            ],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => collect($template['body_parameters'] ?? [])
                                        ->map(fn (mixed $text): array => ['type' => 'text', 'text' => (string) $text])
                                        ->values()
                                        ->all(),
                                ],
                            ],
                        ],
                    ]
                );

            $body = $response->json();

            if ($response->failed() || isset($body['error'])) {
                $status = 'failed';
                $errorMessage = $body['error']['message'] ?? 'WhatsApp API request failed';

                Log::error('Meta WhatsApp failed', [
                    'user_id' => $notifiable->id ?? null,
                    'response' => $body,
                ]);
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $errorMessage = $e->getMessage();

            Log::error('Meta WhatsApp exception', [
                'user_id' => $notifiable->id ?? null,
                'error' => $errorMessage,
            ]);
        }

        $this->log($notifiable, $payload, $status, $errorMessage);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function log(object $notifiable, array $payload, string $status, ?string $errorMessage = null): void
    {
        if (! isset($notifiable->id, $payload['batch_id'])) {
            return;
        }

        $message = $payload['message'] ?? 'WhatsApp reminder';

        if ($errorMessage) {
            $message .= ' | '.$errorMessage;
        }

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'batch_id' => $payload['batch_id'],
            'type' => 'whatsapp',
            'message_type' => $payload['message_type'] ?? 'class_reminder',
            'message' => $message,
            'status' => $status,
            'sent_at' => now(),
        ]);
    }
}
