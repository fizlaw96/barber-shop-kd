<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PhoneVerification;
use App\Services\Whatsapp\WhatsappSender;

/**
 * WhatsApp one-time codes proving the visitor controls a phone number.
 * Verified numbers are remembered in the session only, so a typed number alone never unlocks an account.
 */
class PhoneVerifier
{
    private const MAX_ATTEMPTS = 5;

    private const RESEND_SECONDS = 60;

    public function __construct(private WhatsappSender $whatsapp) {}

    public function isRequired(): bool
    {
        return (bool) config('queue_shop.verify_phone') && ! auth()->check();
    }

    public function isVerified(string $phone): bool
    {
        return ! $this->isRequired()
            || in_array(Customer::normalizePhone($phone), session('verified_phones', []), true);
    }

    /**
     * @return bool false when a code was sent too recently to send another
     */
    public function sendCode(string $phone): bool
    {
        $phone = Customer::normalizePhone($phone);

        $recent = PhoneVerification::where('phone', $phone)
            ->where('created_at', '>', now()->subSeconds(self::RESEND_SECONDS))->exists();
        if ($recent) {
            return false;
        }

        $code = (string) random_int(100000, 999999);
        PhoneVerification::where('phone', $phone)->delete();
        PhoneVerification::create([
            'phone' => $phone,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->whatsapp->send($phone, "Your verification code is {$code}. It expires in 10 minutes.");

        return true;
    }

    public function verify(string $phone, string $code): bool
    {
        $phone = Customer::normalizePhone($phone);
        $record = PhoneVerification::where('phone', $phone)->where('expires_at', '>', now())->latest('id')->first();

        if (! $record || $record->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $record->increment('attempts');

        if (! hash_equals($record->code_hash, hash('sha256', trim($code)))) {
            return false;
        }

        $record->delete();
        session()->put('verified_phones', array_values(array_unique([...session('verified_phones', []), $phone])));

        return true;
    }
}
