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
                'min:1000',
                'max:10000000',
                'decimal:0,2',
            ],
        ], [
            'amount.min' => 'The minimum withdrawal amount is ₦1,000.',
            'amount.max' => 'The maximum withdrawal amount is ₦10,000,000.',
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
                'The minimum withdrawal amount is ₦1,000.',
                'The maximum withdrawal amount is ₦10,000,000.',
                'Enter a valid withdrawal amount with no more than two decimal places.',
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
