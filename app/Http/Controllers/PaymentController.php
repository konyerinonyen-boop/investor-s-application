<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EquityInvestment;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Models\Loan;
use App\Services\InvestmentPaymentService;
use App\Services\LoanPaymentService;
use App\Services\WalletPaymentService;
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
        EquityInvestment $equity
    ): JsonResponse {
        try {
            $result = $this->investmentPaymentService
                ->initializeInvestmentPayment($equity);

            return response()->json([
                'success' => true,
                'message' => 'Equity investment completed successfully.',
                'data' => $result,
            ], 200);

        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);

        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'Insufficient wallet balance.') {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient wallet balance.',
                ], 422);
            }

            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to complete the equity investment.',
            ], 500);

        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to complete the equity investment.',
            ], 500);
        }
    }

    public function fundLoan(Loan $loan): JsonResponse
    {
        try {
            $result = $this->loanPaymentService
                ->initializeLoanFundingPayment($loan);

            return response()->json([
                'success' => true,
                'message' => 'Loan funded successfully.',
                'data' => $result,
            ], 200);

        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);

        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'Insufficient wallet balance.') {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient wallet balance.',
                ], 422);
            }

            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fund this loan.',
            ], 500);

        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fund this loan.',
            ], 500);
        }
    }

public function depositToWallet(
    Request $request,
    WalletPaymentService $walletPaymentService
): JsonResponse {
    $validated = $request->validate([
        'amount' => ['required', 'numeric', 'min:100', 'decimal:0,2'],
        'method' => ['required', 'string', 'in:korapay'],
    ]);

    try {
        $result = $walletPaymentService->initializeDeposit(
            $request->user(),
            (string) $validated['amount'],
            $validated['method']
        );

        return response()->json([
            'success' => true,
            'message' => 'Wallet deposit initialized successfully.',
            'data' => $result,
        ], 201);

    } catch (InvalidArgumentException $exception) {
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
        ], 422);

    } catch (Throwable $exception) {
        report($exception);

        return response()->json([
            'success' => false,
            'message' => 'Unable to initialize wallet deposit.',
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
