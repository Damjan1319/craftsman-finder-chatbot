<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Meta\MetaMessagingHandler;
use App\Services\Meta\MetaSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MetaSignatureVerifier $verifier,
        MetaMessagingHandler $handler,
    ): Response {
        if ($request->isMethod('get')) {
            return $this->verifyWebhook($request);
        }

        $rawBody = $request->getContent();

        if (! $verifier->verify($rawBody, $request->header('X-Hub-Signature-256'))) {
            Log::warning('Meta webhook signature mismatch');

            return response('Forbidden', 403);
        }

        $body = $request->all();

        if ($body !== []) {
            dispatch(function () use ($handler, $body): void {
                try {
                    $handler->handleWebhook($body);
                } catch (\Throwable $exception) {
                    Log::error('Meta webhook error', [
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ]);
                }
            })->afterResponse();
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function verifyWebhook(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('meta.verify_token') && filled($challenge)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Meta webhook verification failed', [
            'mode' => $mode,
            'token_match' => $token === config('meta.verify_token'),
        ]);

        return response('Forbidden', 403);
    }
}
