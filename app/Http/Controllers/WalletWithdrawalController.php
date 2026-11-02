<?php

namespace App\Http\Controllers;

use App\Services\WalletWithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class WalletWithdrawalController extends Controller
{
    public function store(
        Request $request,
        WalletWithdrawalService $withdrawalService
    ): JsonResponse {
        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:100',
                'decimal:0,2',
            ],
        ]);

        try {
            $withdrawal = $withdrawalService->request(
                $request->user(),
                (string) $validated['amount']
            );

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request recorded.',
                'data' => [
                    'withdrawal' => $withdrawal,
                ],
            ], 201);

        } catch (RuntimeException $exception) {
            $knownErrors = [
                'Insufficient wallet balance.',
                'The minimum withdrawal amount is ₦100.',
                'The withdrawal amount cannot have more than two decimal places.',
                'You must verify and save your bank account before withdrawing.',
            ];

            if (in_array($exception->getMessage(), $knownErrors, true)) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }

            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process the withdrawal request.',
            ], 500);

        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process the withdrawal request.',
            ], 500);
        }
    }
}