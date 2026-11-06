<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EquityListing;
use App\Services\EquityMarketplaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

class EquityMarketplaceController extends Controller
{
    public function __construct(
        protected EquityMarketplaceService $marketplaceService
    ) {}

    /**
     * Browse active marketplace listings.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'equity_round_id' => [
                'sometimes',
                'integer',
                'exists:equity_rounds,id',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = EquityListing::query()
            ->with([
                'seller:id,first_name,last_name',
                'round.product',
            ])
            ->where('status', 'active')
            ->where('units_remaining', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });

        if (isset($filters['equity_round_id'])) {
            $query->where(
                'equity_round_id',
                $filters['equity_round_id']
            );
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->latest()
                ->paginate($filters['per_page'] ?? 15),
        ]);
    }

    /**
     * Create a listing.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'equity_round_id' => [
                'required',
                'integer',
                'exists:equity_rounds,id',
            ],
            'units' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,6',
            ],
            'unit_price' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,6',
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after:now',
            ],
        ]);

        try {
            $listing = $this->marketplaceService->createListing(
                $request->user(),
                $data['equity_round_id'],
                $data['units'],
                $data['unit_price'],
                isset($data['expires_at'])
                    ? Carbon::parse($data['expires_at'])
                    : null,
            );

            return response()->json([
                'success' => true,
                'message' => 'Equity listing created successfully.',
                'data' => [
                    'listing' => $listing->load([
                        'seller',
                        'round.product',
                    ]),
                ],
            ], 201);

        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel a listing.
     */
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'listing_id' => [
                'required',
                'integer',
                'exists:equity_listings,id',
            ],
        ]);

        try {
            $cancelled = $this->marketplaceService->cancelListing(
                $request->user(),
                $data['listing_id'],
            );

            return response()->json([
                'success' => true,
                'message' => 'Listing cancelled successfully.',
                'data' => [
                    'listing' => $cancelled,
                ],
            ]);

        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }
    }

    /**
     * Purchase units from a listing.
     */
    public function purchase(Request $request)
    {
        $data = $request->validate([
            'listing_id' => [
                'required',
                'integer',
                'exists:equity_listings,id',
            ],
            'units' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,6',
            ],
        ]);

        try {
            $trade = $this->marketplaceService->buyListing(
                $request->user(),
                $data['listing_id'],
                $data['units'],
            );

            return response()->json([
                'success' => true,
                'message' => 'Equity purchased successfully.',
                'data' => [
                    'trade' => $trade,
                ],
            ], 201);

        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
