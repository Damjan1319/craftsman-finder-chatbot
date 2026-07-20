<?php

namespace App\Services\Meta;

class MetaSignatureVerifier
{
    public function verify(string $rawBody, ?string $signatureHeader): bool
    {
        if (config('meta.skip_signature')) {
            return true;
        }

        $secret = config('meta.app_secret');

        if (blank($secret) || blank($signatureHeader)) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signatureHeader);
    }
}
