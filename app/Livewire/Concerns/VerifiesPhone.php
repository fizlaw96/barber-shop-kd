<?php

namespace App\Livewire\Concerns;

use App\Services\PhoneVerifier;

trait VerifiesPhone
{
    public bool $awaitingCode = false;

    public string $code = '';

    /**
     * Returns true once the phone is verified. Otherwise sends or checks a code and returns false.
     */
    protected function phoneIsVerified(string $phone): bool
    {
        $verifier = app(PhoneVerifier::class);

        if ($verifier->isVerified($phone)) {
            return true;
        }

        if ($this->awaitingCode && $this->code !== '') {
            if ($verifier->verify($phone, $this->code)) {
                $this->awaitingCode = false;

                return true;
            }

            $this->addError('code', 'That code is wrong or expired.');

            return false;
        }

        $sent = $verifier->sendCode($phone);
        $this->awaitingCode = true;
        $this->addError('code', $sent ? 'We sent a 6-digit code to your WhatsApp. Enter it below.' : 'A code was sent a moment ago. Please check WhatsApp.');

        return false;
    }
}
