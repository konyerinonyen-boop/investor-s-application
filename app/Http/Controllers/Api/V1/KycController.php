<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kyc\SubmitKycRequest;
use App\Services\KycService;
use Illuminate\Http\Request;
use App\Services\PaystackService;
use Illuminate\Support\Facades\Log;
class KycController extends Controller
{
   public function __construct(
    private readonly KycService $kycService,
    private readonly PaystackService $paystack,
) {}

    public function status(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->kycService->status(
                $request->user()
            ),
        ]);
    }

    public function update(SubmitKycRequest $request)
    {
        $profile = $this->kycService->update(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'KYC details updated successfully.',
            'data' => $profile,
        ]);
    }

    public function submit(SubmitKycRequest $request)
    {
        $profile = $this->kycService->submit(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'KYC submission saved successfully.',
            'data' => $profile,
        ], 202);
    }

    public function startSumsub(Request $request)
    {
        $result = $this->kycService->startSumsub(
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Sumsub verification session created.',
            'data' => $result,
        ]);
    }

    public function banks()
{
    try {
        return response()->json([
            'success' => true,
            'data' => $this->paystack->listBanks(),
        ]);
    } catch (\Throwable $exception) {
        Log::error('Unable to load Paystack banks.', [
            'exception' => $exception::class,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to load banks right now. Please try again shortly.',
        ], 503);
    }
}
}
