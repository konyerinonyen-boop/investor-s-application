<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Kyc\SubmitKycRequest;
use App\Models\KycProfile;
use App\Services\BytidVerificationService;
use App\Services\KycService;
use Illuminate\Http\Request;

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
    $profile = \App\Models\KycProfile::query()
        ->where('user_id', $request->user()->id)
        ->firstOrFail();

    $result = $this->kycService->startBytidLiveness($profile, $bytid);

    return response()->json([
        'success' => true,
        'message' => 'Liveness session started successfully.',
        'data' => $result,
    ]);
}

public function completeBytidLiveness(
    $session_id,
    Request $request,
   BytidVerificationService $bytid
) {


    $profile = KycProfile::query()
        ->where('user_id', $request->user()->id)
        ->firstOrFail();

    $result = $this->kycService->completeBytidLiveness(
        $profile,
        $session_id,
        $bytid
    );

    $httpStatus = $result['http_status'];

    unset($result['http_status']);

    return response()->json($result, $httpStatus);
}


}
