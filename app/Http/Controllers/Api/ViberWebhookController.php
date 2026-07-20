<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Viber\ViberSignatureVerifier;
use App\Services\Viber\ViberWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ViberWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        ViberSignatureVerifier $signatureVerifier,
        ViberWebhookHandler $handler,
    ): JsonResponse {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Viber-Content-Signature');

        if (! $signatureVerifier->verify($rawBody, $signature)) {
            Log::warning('Viber webhook signature verification failed');

            return response()->json(['status' => 1, 'status_message' => 'invalid signature'], 403);
        }

        $payload = $request->all();

        Log::debug('Viber webhook received', [
            'event' => $payload['event'] ?? null,
        ]);

        $response = $handler->handle($payload);

        if (isset($response['type'])) {
            return response()->json($response);
        }

        return response()->json($response);
    }
}
