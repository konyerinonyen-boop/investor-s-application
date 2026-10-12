<?php

namespace App\Http\Controllers;

use App\Factories\PaymentGatwayFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\paymentMethodRequest;
use App\Models\EquityInvestment;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Models\Loan;
use App\Services\InvestmentPaymentService;
use App\Services\LoanPaymentService;
use Illuminate\Validation\Rule;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct( 
        protected PaymentService $paymentService,
        protected LoanPaymentService $loanPaymentService,
        protected InvestmentPaymentService $investmentPaymentService

     )
    {
        
    }
        public function initializeInvestmentPayment(
            EquityInvestment $equity,
            paymentMethodRequest $request
            )
    {
                    
        try {
            $payment = $this->investmentPaymentService->initializeInvestmentPayment($equity, $request['method']);

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

        public function fundLoan(
            paymentMethodRequest $request,
            Loan $loan,
        ) {
            $validated = $request->validated();

            try {
                $result = $this->loanPaymentService->initializeLoanFundingPayment(
                    $loan,
                    $validated['method']
                );

                return response()->json([
                    'message' => 'Loan funding payment initialized successfully.',
                    'data' => [
                        'payment' => $result['payment'],
                        'gateway' => $result['gateway'],
                    ],
                ], 201);

            } catch (InvalidArgumentException $exception) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);

            } catch (Throwable $exception) {
                report($exception);

                return response()->json([
                    'message' => 'Unable to initialize the loan funding payment.',
                ], 500);
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
