<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\KycService;
use App\Services\SumsubVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SumsubWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        SumsubVerificationService $sumsub,
        KycService $kycService,
    ) {
        $rawBody = $request->getContent();

        $payload = $sumsub->verifyWebhook(
            $rawBody,
            $request->header('x-payload-digest'),
            $request->header('x-payload-digest-alg', 'HMAC_SHA256'),
        );

        if ($payload === null) {
            Log::warning('Sumsub: Webhook signature or payload validation failed.', [
                'ip' => $request->ip(),
                'algorithm' => $request->header('x-payload-digest-alg'),
            ]);

            return response()->json([
                'message' => 'Invalid webhook signature or payload.',
            ], 401);
        }

        Log::info('Sumsub: Webhook received.', [
            'event_type' => $payload['type'] ?? 'unknown',
            'applicant_id' => $payload['applicantId'] ?? null,
            'external_user_id' => $payload['externalUserId']
                ?? $payload['applicant']['externalUserId']
                ?? null,
        ]);

        $kycService->processSumsubWebhook($payload);

        Log::info('Sumsub: Webhook processing completed.', [
            'event_type' => $payload['type'] ?? 'unknown',
            'applicant_id' => $payload['applicantId'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }
}