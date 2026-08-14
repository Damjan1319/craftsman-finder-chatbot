<?php

namespace App\Services\Meta;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MetaHttp
{
    public static function client(): PendingRequest
    {
        $client = Http::timeout(15)->connectTimeout(5);

        if (! config('meta.verify_ssl')) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }
}
