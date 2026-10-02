<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Investment\StoreEquityInvestmentRequest;
use App\Http\Requests\Investment\StoreLoanApplicationRequest;
use App\Services\InvestmentService;
use InvalidArgumentException;

class InvestmentController
{
    public function __construct(protected InvestmentService $investmentService)
    {
    }

    public function equity(StoreEquityInvestmentRequest $request)
    {
        try {
            $investment = $this->investmentService->createEquityInvestment($request->user(), $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Equity commitment recorded successfully.',
                'data' => ['investment' => $investment],
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }
    }

    public function loan(StoreLoanApplicationRequest $request)
    {
        try {
            $loan = $this->investmentService->createLoanApplication($request->user(), $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Loan application created successfully.',
                'data' => ['loan' => $loan],
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }
    }
}
