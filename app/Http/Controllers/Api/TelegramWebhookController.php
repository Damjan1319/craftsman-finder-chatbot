<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramUpdateHandler $handler): Response
    {
        $secret = config('telegram.webhook_secret');

        if (filled($secret) && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            Log::warning('Telegram webhook secret mismatch');

            return response('', 403);
        }

        $update = $request->all();

        if ($update !== []) {
            dispatch(function () use ($handler, $update): void {
                try {
                    $handler->handle($update);
                } catch (\Throwable $exception) {
                    Log::error('Telegram webhook error', [
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ]);
                }
            })->afterResponse();
        }

        return response('', 200);
    }
}
