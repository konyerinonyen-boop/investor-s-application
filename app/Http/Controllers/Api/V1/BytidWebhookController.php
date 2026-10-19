<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BytidWebhookEvent;
use App\Services\BytidWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BytidWebhookController
{
    public function __invoke(Request $request, BytidWebhookService $service): Response
    {
        $rawBody = $request->getContent();
        $signatureHeader = (string) $request->header('Bytid-Signature', '');
        $secret = (string) config('services.bytid.webhook_secret', '');

        if ($secret === '' || ! $this->signatureIsValid($signatureHeader, $rawBody, $secret)) {
            Log::warning('Rejected Bytid webhook: invalid signature.');

            return response()->json(['success' => false, 'message' => 'Invalid webhook signature.'], 401);
        }

        $payload = json_decode($rawBody, true);
        $eventType = (string) $request->header('Bytid-Event', '');
        $eventId = (string) $request->header('Bytid-Event-Id', '');

        if (! is_array($payload) || $eventType === '' || $eventId === '') {
            return response()->json(['success' => false, 'message' => 'Malformed webhook payload.'], 400);
        }

        try {
            $service->process($eventId, $eventType, $payload);
        } catch (\Throwable $exception) {
            report($exception);

            // A 5xx response tells Bytid to retry; do not acknowledge failed processing.
            return response()->json(['success' => false, 'message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['success' => true], 200);
    }

   
private function signatureIsValid(string $header, string $rawBody, string $secret): bool
{
    if (
        ! preg_match('/(?:^|,)\s*t=(\d+)(?:,|$)/', $header, $timeMatch)
        || ! preg_match('/(?:^|,)\s*v1=([a-f0-9]{64})(?:,|$)/i', $header, $signatureMatch)
    ) {
        return false;
    }

    $timestamp = (int) $timeMatch[1];

    if (abs(time() - $timestamp) > 300) {
        return false;
    }

    $expected = hash_hmac(
        'sha256',
        $timestamp . '.' . $rawBody,
        $secret
    );

    return hash_equals($expected, strtolower($signatureMatch[1]));
}

}
