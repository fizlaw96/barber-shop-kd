<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Log;

class LogWhatsappSender implements WhatsappSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('WhatsApp (log driver)', ['to' => $phone, 'message' => $message]);
    }
}
