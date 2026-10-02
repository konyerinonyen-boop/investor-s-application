<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PortfolioService;
use Illuminate\Http\Request;

class PortfolioController
{
    public function __construct(protected PortfolioService $portfolioService)
    {
    }

    public function summary(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->portfolioService->summary($request->user()),
        ]);
    }
}
