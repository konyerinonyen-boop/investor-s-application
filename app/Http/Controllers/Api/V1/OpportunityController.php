<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\OpportunityService;
use Illuminate\Http\Request;

class OpportunityController
{
    public function __construct(protected OpportunityService $opportunityService)
    {
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->opportunityService->list($request),
        ]);
    }

    public function show(string $id)
    {
        return response()->json([
            'success' => true,
            'data' => $this->opportunityService->show($id),
        ]);
    }
}
