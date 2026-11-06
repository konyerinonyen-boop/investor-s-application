<?php

namespace App\Services;

use App\Models\EquityHolding;
use App\Models\EquityListing;
use App\Models\EquityMarketplaceTrade;
use App\Models\EquityOwnershipTransaction;
use App\Models\EquityRound;
use App\Models\User;
use App\Models\Wallet;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class EquityMarketplaceService
{
    public function __construct(
        private WalletService $walletService,
    ) {}

    /**
     * Create a new equity listing.
     */
    public function createListing(
        User $seller,
        int $equityRoundId,
        string $units,
        string $unitPrice,
        ?\DateTimeInterface $expiresAt = null,
    ): EquityListing {
        $unitsValue = $this->positiveDecimal($units, 'Units');
        $priceValue = $this->positiveDecimal($unitPrice, 'Unit price');

        return DB::transaction(function () use (
            $seller,
            $equityRoundId,
            $unitsValue,
            $priceValue,
            $expiresAt,
        ) {
            $round = EquityRound::query()->findOrFail($equityRoundId);

            // Lock the seller's holding to prevent concurrent over-listing.
            $holding = EquityHolding::query()
                ->where('investor_id', $seller->id)
                ->where('equity_round_id', $round->id)
                ->lockForUpdate()
                ->first();

            if (! $holding) {
                throw new RuntimeException(
                    'You do not own any units in this equity round.'
                );
            }

            // Count units already committed to active, unexpired listings.
            $alreadyListed = EquityListing::query()
                ->where('seller_id', $seller->id)
                ->where('equity_round_id', $round->id)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->sum('units_remaining');

            $availableUnits = BigDecimal::of((string) $holding->units)
                ->minus((string) $alreadyListed);

            if ($unitsValue->isGreaterThan($availableUnits)) {
                throw new RuntimeException(
                    'You do not have enough available units to list.'
                );
            }

            return EquityListing::create([
                'seller_id' => $seller->id,
                'equity_round_id' => $round->id,
                'units_listed' => (string) $unitsValue,
                'units_remaining' => (string) $unitsValue,
                'unit_price' => (string) $priceValue,
                'status' => 'active',
                'reference' => 'equity-listing-'.Str::uuid(),
                'expires_at' => $expiresAt,
            ]);
        }, 3);
    }

    /**
     * Cancel an investor's own active listing.
     */
    public function cancelListing(
        User $seller,
        int $listingId,
    ): EquityListing {
        return DB::transaction(function () use ($seller, $listingId) {
            $listing = EquityListing::query()
                ->whereKey($listingId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $listing->seller_id !== (int) $seller->id) {
                throw new RuntimeException(
                    'You cannot cancel another investor\'s listing.'
                );
            }

            if ($listing->status !== 'active') {
                throw new RuntimeException(
                    'This listing is no longer active.'
                );
            }

            $listing->update([
                'status' => 'cancelled',
            ]);

            return $listing->refresh();
        }, 3);
    }

    /**
     * Buy some or all of the remaining units in a listing.
     */
    public function buyListing(
        User $buyer,
        int $listingId,
        string $units,
    ): EquityMarketplaceTrade {
        $unitsValue = $this->positiveDecimal($units, 'Purchase units');

        return DB::transaction(function () use (
            $buyer,
            $listingId,
            $unitsValue,
        ) {
            // Serialize purchases against this listing.
            $listing = EquityListing::query()
                ->whereKey($listingId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($listing->status !== 'active') {
                throw new RuntimeException(
                    'This listing is no longer available.'
                );
            }

            if (
                $listing->expires_at !== null
                && $listing->expires_at->isPast()
            ) {
                $listing->update(['status' => 'expired']);

                throw new RuntimeException(
                    'This listing has expired.'
                );
            }

            if ((int) $listing->seller_id === (int) $buyer->id) {
                throw new RuntimeException(
                    'You cannot purchase your own listing.'
                );
            }

            $remainingUnits = BigDecimal::of(
                (string) $listing->units_remaining
            );

            if ($unitsValue->isGreaterThan($remainingUnits)) {
                throw new RuntimeException(
                    'The requested units exceed the available listing units.'
                );
            }

            // Lock the seller's holding before transferring ownership.
            $sellerHolding = EquityHolding::query()
                ->where('investor_id', $listing->seller_id)
                ->where('equity_round_id', $listing->equity_round_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                BigDecimal::of((string) $sellerHolding->units)
                    ->isLessThan($unitsValue)
            ) {
                throw new RuntimeException(
                    'The seller does not have enough ownership units.'
                );
            }

            // Calculate the amount using decimal arithmetic.
            $unitPrice = BigDecimal::of((string) $listing->unit_price);

            $totalAmount = $unitsValue
                ->multipliedBy($unitPrice)
                ->toScale(2, RoundingMode::HalfUp);

            if ($totalAmount->isLessThanOrEqualTo('0')) {
                throw new RuntimeException(
                    'The purchase amount must be greater than zero.'
                );
            }

            $seller = User::query()->findOrFail($listing->seller_id);

            // This marketplace currently uses NGN.
            $buyerWallet = Wallet::firstOrCreate(
                ['user_id' => $buyer->id],
                ['currency' => 'NGN', 'balance' => '0.00'],
            );

            $sellerWallet = Wallet::firstOrCreate(
                ['user_id' => $seller->id],
                ['currency' => 'NGN', 'balance' => '0.00'],
            );

            if (
                $buyerWallet->currency !== 'NGN'
                || $sellerWallet->currency !== 'NGN'
            ) {
                throw new RuntimeException(
                    'Both investor wallets must use NGN for this trade.'
                );
            }

            $tradeReference = 'equity-trade-'.Str::uuid();

            // Create the trade inside the transaction.
            $trade = EquityMarketplaceTrade::create([
                'listing_id' => $listing->id,
                'seller_id' => $seller->id,
                'buyer_id' => $buyer->id,
                'equity_round_id' => $listing->equity_round_id,
                'units' => (string) $unitsValue,
                'unit_price' => (string) $unitPrice,
                'total_amount' => (string) $totalAmount,
                'currency' => 'NGN',
                'status' => 'completed',
                'reference' => $tradeReference,
                'executed_at' => now(),
                'metadata' => [
                    'listing_reference' => $listing->reference,
                ],
            ]);

            // Debit buyer.
            $this->walletService->debit(
                $buyer,
                (string) $totalAmount,
                'equity_marketplace_purchase',
                $tradeReference.'-buyer-debit',
                'Purchase of equity units',
                $trade,
                [
                    'trade_reference' => $tradeReference,
                    'listing_id' => $listing->id,
                ],
            );

            // Credit seller.
            $this->walletService->credit(
                $seller,
                (string) $totalAmount,
                'equity_marketplace_sale',
                $tradeReference.'-seller-credit',
                'Sale of equity units',
                $trade,
                [
                    'trade_reference' => $tradeReference,
                    'listing_id' => $listing->id,
                ],
            );

            // Reduce the seller's ownership.
            $sellerHolding->units = (string) BigDecimal::of(
                (string) $sellerHolding->units
            )->minus($unitsValue);

            $sellerHolding->save();

            // Create or retrieve the buyer's holding, then lock it.
            DB::table('equity_holdings')->insertOrIgnore([
                'investor_id' => $buyer->id,
                'equity_round_id' => $listing->equity_round_id,
                'units' => '0.000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $buyerHolding = EquityHolding::query()
                ->where('investor_id', $buyer->id)
                ->where('equity_round_id', $listing->equity_round_id)
                ->lockForUpdate()
                ->firstOrFail();

            $buyerHolding->units = (string) BigDecimal::of(
                (string) $buyerHolding->units
            )->plus($unitsValue);

            $buyerHolding->save();

            // Record the seller's ownership reduction.
            EquityOwnershipTransaction::create([
                'investor_id' => $seller->id,
                'equity_round_id' => $listing->equity_round_id,
                'marketplace_trade_id' => $trade->id,
                'counterparty_investor_id' => $buyer->id,
                'transaction_type' => 'marketplace_sale',
                'units_delta' => (string) $unitsValue->negated(),
                'unit_price' => (string) $unitPrice,
                'total_amount' => (string) $totalAmount,
                'currency' => 'NGN',
                'reference' => $tradeReference.'-seller-ownership',
                'occurred_at' => now(),
                'metadata' => [
                    'listing_id' => $listing->id,
                ],
            ]);

            // Record the buyer's ownership increase.
            EquityOwnershipTransaction::create([
                'investor_id' => $buyer->id,
                'equity_round_id' => $listing->equity_round_id,
                'marketplace_trade_id' => $trade->id,
                'counterparty_investor_id' => $seller->id,
                'transaction_type' => 'marketplace_purchase',
                'units_delta' => (string) $unitsValue,
                'unit_price' => (string) $unitPrice,
                'total_amount' => (string) $totalAmount,
                'currency' => 'NGN',
                'reference' => $tradeReference.'-buyer-ownership',
                'occurred_at' => now(),
                'metadata' => [
                    'listing_id' => $listing->id,
                ],
            ]);

            // Reduce the listing's remaining units.
            $newRemainingUnits = $remainingUnits->minus($unitsValue);

            $listing->units_remaining = (string) $newRemainingUnits;

            if ($newRemainingUnits->isEqualTo('0')) {
                $listing->status = 'sold_out';
            }

            $listing->save();

            return $trade->load([
                'listing',
                'seller',
                'buyer',
                'round',
            ]);
        }, 3);
    }

    /**
     * Validate positive decimal values with at most six decimal places.
     */
    private function positiveDecimal(
        string $value,
        string $field,
    ): BigDecimal {
        try {
            $decimal = BigDecimal::of($value);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException(
                "{$field} must be a valid decimal number.",
                previous: $exception,
            );
        }

        if (
            $decimal->getScale() > 6
            || $decimal->isLessThanOrEqualTo('0')
        ) {
            throw new InvalidArgumentException(
                "{$field} must be greater than zero and have at most six decimal places."
            );
        }

        return $decimal->toScale(6);
    }
}
