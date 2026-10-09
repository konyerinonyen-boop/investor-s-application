<?php

namespace App\Http\Controllers;

use App\Factories\PaymentGatwayFactory;
use App\Http\Controllers\Controller;

use App\Http\Requests\paymentMethodRequest;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Trait\Api\V1\UserTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct( protected PaymentService $paymentService, private PaymentGatwayFactory $factory )
    {
        
    }
        public function initializeInvestmentPayment( paymentMethodRequest $request)
    {
       $user = auth()->user();
        $request = $request->validated();
        
       $investment = $user->investments()
        ->findOrFail($request['investment_id']);
        

        try {
            $payment = $this->paymentService->initializeInvestmentPayment($investment, $request['method']);

            return response()->json([
                'success' => true,
                'message' => 'Payment Intialized Successfully',
                'data' => ['investment' => $payment],
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }
    }


public function verifyPayment(
    Request $request,
    Payment $payment,
): JsonResponse {
    // Make sure the payment belongs to the logged-in investor.
    if ((int) $payment->user_id !== (int) $request->user()->id) {
        return response()->json([
            'success' => false,
            'message' => 'You are not authorized to verify this payment.',
        ], 403);
    }

    try {
        $result = $this->paymentService->verifyPayment($payment);

        return response()->json([
            'success' => true,
            'message' => 'Paystack verification completed.',
            'data' => $result,
        ]);
    } catch (RuntimeException $exception) {
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
        ], 422);
    }
}

    public function webhook(Request $request, string $provider)
    {
        
      try
      {
        $this->paymentService->webhook($request, $provider);

        }catch (\Exception $e) {
              Log::info('Wehook',[
                    'result' => $e->getMessage(),
                ]);
         }
    }

}
