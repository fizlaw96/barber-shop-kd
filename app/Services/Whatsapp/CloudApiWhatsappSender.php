<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends messages through the Meta WhatsApp Cloud API.
 * Free-form text only reaches customers inside the 24h service window;
 * outside it the message is re-sent through WHATSAPP_FALLBACK_TEMPLATE, a one-variable body template ({{1}}) approved in Meta.
 */
class CloudApiWhatsappSender implements WhatsappSender
{
    public function send(string $phone, string $message): void
    {
        $config = config('queue_shop.whatsapp');

        $response = Http::withToken($config['token'])
            ->post("https://graph.facebook.com/{$config['api_version']}/{$config['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        // 131047: outside the 24h customer-service window, only approved templates can be sent.
        if ($response->json('error.code') === 131047 && $config['fallback_template']) {
            $response = Http::withToken($config['token'])
                ->post("https://graph.facebook.com/{$config['api_version']}/{$config['phone_number_id']}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => $config['fallback_template'],
                        'language' => ['code' => $config['template_language']],
                        'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $message]]]],
                    ],
                ]);
        }

        if ($response->failed()) {
            Log::warning('WhatsApp send failed', ['to' => $phone, 'status' => $response->status(), 'body' => $response->body()]);
        }
    }
}
