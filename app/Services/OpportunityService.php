<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;

class OpportunityService
{
    public function list(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $query = Product::query()->with(['equityRounds', 'loanOffers']);

        if ($request->filled('instrument_type')) {
            $query->where('instrument_type', $request->instrument_type);
        }

        return $query->paginate($perPage);
    }

    public function show(string $id): Product
    {
        return Product::with(['equityRounds', 'loanOffers'])->findOrFail($id);
    }
}
