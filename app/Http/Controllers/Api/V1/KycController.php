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

        // Store files privately. Do not call Bytid from this request.
        $data = $this->kycService->storeUploadedDocuments($data);

        // Persist the profile and its Bytid reference first.
        $payload = $this->kycService->submit(
            $user,
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Identity document saved. Start liveness verification to continue.',
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