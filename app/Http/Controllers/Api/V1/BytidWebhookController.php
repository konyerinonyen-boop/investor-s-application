<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\BytidWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BytidWebhookController
{
    public function __invoke(
        Request $request,
        BytidWebhookService $service
    ): Response {

        Log::info(
    'BYTID WEBHOOK RECEIVED',
    [
        'method' => request()->method(),
        'path' => request()->path(),
        'event_type' => request()->header('Bytid-Event'),
        'event_id_present' => request()->hasHeader('Bytid-Event-Id'),
    ]
);
        $rawBody = $request->getContent();
        $eventType = (string) $request->header('Bytid-Event', '');
        $eventId = (string) $request->header('Bytid-Event-Id', '');

        Log::info('Bytid webhook: request received', [
            'event_id' => $eventId ?: null,
            'event_type' => $eventType ?: null,
            'body_bytes' => strlen($rawBody),
            'signature_header_present' => $request->headers->has('Bytid-Signature'),
            'request_id' => $request->header('X-Request-Id'),
        ]);

        $secret = (string) config('services.bytid.webhook_secret', '');
        $signatureHeader = (string) $request->header('Bytid-Signature', '');

        if ($secret === '') {
            Log::critical('Bytid webhook: webhook secret is not configured.');

            return response()->json([
                'success' => false,
                'message' => 'Webhook security configuration error.',
            ], 500);
        }

        if (! $this->signatureIsValid($signatureHeader, $rawBody, $secret)) {
            Log::warning('Bytid webhook: signature validation failed', [
                'event_id' => $eventId ?: null,
                'event_type' => $eventType ?: null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        Log::info('Bytid webhook: signature validated', [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        $payload = json_decode($rawBody, true);

        if (! is_array($payload) || $eventType === '' || $eventId === '') {
            Log::warning('Bytid webhook: malformed request', [
                'event_id_present' => $eventId !== '',
                'event_type_present' => $eventType !== '',
                'json_valid' => is_array($payload),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Malformed webhook payload.',
            ], 400);
        }

        try {
            Log::info('Bytid webhook: processing started', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'payload_top_level_keys' => array_keys($payload),
                'data_keys' => is_array($payload['data'] ?? null)
                    ? array_keys($payload['data'])
                    : [],
            ]);

            $service->process($eventId, $eventType, $payload);

            Log::info('Bytid webhook: processing completed', [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            return response()->json(['success' => true], 200);
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Bytid webhook: processing failed', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed.',
            ], 500);
        }
    }

    private function signatureIsValid(
        string $header,
        string $rawBody,
        string $secret
    ): bool {
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
