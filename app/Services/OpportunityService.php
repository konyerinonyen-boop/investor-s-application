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
            $type = $request->query('instrument_type');

            if (in_array($type, ['equity', 'loan'], true)) {
                $query->whereIn('instrument_type', [
                    $type,
                    'both',
                ]);
            } elseif ($type === 'both') {
                $query->where('instrument_type', 'both');
            }
        }

        return $query->paginate($perPage);
    }

    public function show(string $id): Product
    {
        return Product::with(['equityRounds', 'loanOffers'])->findOrFail($id);
    }
}
