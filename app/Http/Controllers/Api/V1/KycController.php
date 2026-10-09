<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Kyc\StartKycRequest;
use App\Http\Requests\Kyc\SubmitKycRequest;
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
}
