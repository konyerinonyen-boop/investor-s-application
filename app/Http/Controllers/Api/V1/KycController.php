<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kyc\SubmitKycRequest;
use App\Models\KycProfile;
use App\Services\BytidVerificationService;
use App\Services\KycService;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function __construct(
        protected KycService $kycService,
        protected BytidVerificationService $bytidVerificationService
    ) {}

    public function update(SubmitKycRequest $request)
    {
        $payload = $this->kycService->update(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'KYC updated successfully.',
            'data' => $payload['profile'],
        ]);
    }

    public function submit(SubmitKycRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        // 1. Store the uploaded documents on Laravel's private local disk.
        $data = $this->kycService->storeUploadedDocuments($data);

        // 2. Submit the stored documents to Bytid.
        $bytidResult = $this->bytidVerificationService->submitStoredDocument(
            userId: $user->id,
            documentType: $data['id_document_type'],
            frontPath: $data['id_document_path_front'],
            backPath: $data['id_document_path_back'] ?? null,
        );

        // 3. Save the KYC submission and Bytid reference.
        $payload = $this->kycService->submit(
            $user,
            $data,
            $bytidResult
        );

        return response()->json([
            'success' => true,
            'message' => 'Identity document submitted successfully. Verification is pending.',
            'data' => [
                'kyc_status' => $payload['user']->kyc_status,
                'profile' => $payload['profile'],
            ],
        ], 202);
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

    public function startBytidLiveness(Request $request)
    {
        $profile = KycProfile::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $result = $this->kycService->startBytidLiveness(
            $profile,
            $this->bytidVerificationService
        );

        return response()->json([
            'success' => true,
            'message' => 'Liveness session started successfully.',
            'data' => $result,
        ]);
    }

    public function completeBytidLiveness(
        string $session_id,
        Request $request
    ) {
        $profile = KycProfile::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $result = $this->kycService->completeBytidLiveness(
            $profile,
            $session_id,
            $this->bytidVerificationService
        );

        $httpStatus = $result['http_status'];

        unset($result['http_status']);

        return response()->json($result, $httpStatus);
    }
}