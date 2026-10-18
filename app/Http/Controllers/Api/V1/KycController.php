<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Kyc\SubmitKycRequest;
use App\Services\KycService;
use Illuminate\Http\Request;
use App\Services\BytidVerificationService;
class KycController
{
    public function __construct(protected KycService $kycService)
    {
    }

    public function update(SubmitKycRequest $request)
    {
        $payload = $this->kycService->update($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'KYC Updated',
            'data' => $payload['profile'],
        ]);
    }

    public function submit(SubmitKycRequest $request)
    {
        $payload = $this->kycService->submit($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'KYC submitted for review.',
            'data' => $payload['profile'],
        ]);
    }

    public function status(Request $request)
    {
        $payload = $this->kycService->status($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'kyc_status' => $payload['kyc_status'],
                'profile' => $payload['profile'],
            ],
        ]);
    }

    
public function startBytidLiveness(
    Request $request,
    BytidVerificationService $bytid
) {
    $profile = $request->user()->kycProfile()->first();

    if (
        ! $profile ||
        ! $profile->id_document_path_front ||
        ! $profile->id_document_type
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Submit your identity document before starting verification.',
        ], 422);
    }

    try {
        $session = $bytid->startLiveness($profile);

        return response()->json([
            'success' => true,
            'message' => 'Liveness session created.',
            'data' => $session,
        ]);
    } catch (\RuntimeException $exception) {
        report($exception);

        return response()->json([
            'success' => false,
            'message' => 'Could not start identity verification. Please try again.',
        ], 502);
    }
}

public function completeBytidLiveness(
    Request $request,
    BytidVerificationService $bytid
) {
    $validated = $request->validate([
        'session_id' => ['required', 'string', 'max:100'],
    ]);

    $user = $request->user();
    $profile = $user->kycProfile()->first();

    if (
        ! $profile ||
        ! $profile->selfie_verification_reference ||
        ! hash_equals(
            $profile->selfie_verification_reference,
            $validated['session_id']
        )
    ) {
        return response()->json([
            'success' => false,
            'message' => 'This verification session does not belong to your KYC application.',
        ], 403);
    }

    try {
        $session = $bytid->getLivenessSession(
            $validated['session_id']
        );

        $livenessStatus = $session['status'] ?? 'pending';

        if ($livenessStatus === 'pending') {
            return response()->json([
                'success' => true,
                'message' => 'Liveness verification is still in progress.',
                'data' => [
                    'liveness_status' => 'pending',
                ],
            ], 202);
        }

        if ($livenessStatus !== 'passed') {
            $profile->forceFill([
                'selfie_verification_status' => 'rejected',
                'selfie_verified_at' => null,
                'status' => 'rejected',
                'rejection_reason' =>
                    'Liveness verification was not passed.',
            ])->save();

            $user->forceFill([
                'kyc_status' => 'rejected',
            ])->save();

            return response()->json([
                'success' => false,
                'message' => 'Liveness verification was not passed. Start a new session to retry.',
                'data' => [
                    'liveness_status' => $livenessStatus,
                ],
            ], 422);
        }

        $profile->forceFill([
            'selfie_verification_status' => 'verified',
            'selfie_verified_at' => now(),
        ])->save();

        $documentResult = $bytid->verifyDocument(
            $profile->fresh(),
            $validated['session_id']
        );

        $profile->refresh();

        if ($profile->identity_verification_status === 'rejected') {
            $profile->forceFill([
                'status' => 'rejected',
                'rejection_reason' =>
                    'Identity document verification was rejected.',
            ])->save();

            $user->forceFill([
                'kyc_status' => 'rejected',
            ])->save();
        } else {
            // Automated checks are not final admin approval.
            $profile->forceFill([
                'status' => 'pending',
                'rejection_reason' => null,
            ])->save();

            $user->forceFill([
                'kyc_status' => 'pending',
            ])->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Bytid verification result received.',
            'data' => [
                'liveness_status' =>
                    $profile->selfie_verification_status,
                'identity_status' =>
                    $profile->identity_verification_status,
                'kyc_status' => $profile->status,
                'document_result' => [
                    'status' => $documentResult['status'] ?? 'unknown',
                    'reasons' => $documentResult['reasons'] ?? [],
                ],
            ],
        ]);
    } catch (\RuntimeException $exception) {
        report($exception);

        return response()->json([
            'success' => false,
            'message' => 'We could not complete verification with Bytid. Please try again later.',
        ], 502);
    }
}

}
