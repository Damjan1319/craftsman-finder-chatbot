<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TelegramHttp
{
    public static function client(): PendingRequest
    {
        $client = Http::timeout(15)->connectTimeout(5);

        if (! config('telegram.verify_ssl')) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }
}
