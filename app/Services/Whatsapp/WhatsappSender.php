<?php

namespace App\Services\Whatsapp;

interface WhatsappSender
{
    /**
     * Send a plain text WhatsApp message to a phone number (digits only, with country code).
     */
    public function send(string $phone, string $message): void;
}
