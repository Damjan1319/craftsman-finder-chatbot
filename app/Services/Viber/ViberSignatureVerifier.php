<?php

namespace App\Services\Viber;

class ViberSignatureVerifier
{
    public function verify(string $rawBody, ?string $signature): bool
    {
        if (config('viber.skip_signature')) {
            return true;
        }

        $token = config('viber.auth_token');

        if (blank($token) || blank($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $token);

        return hash_equals($expected, $signature);
    }
}
