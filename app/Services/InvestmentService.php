<?php

namespace App\Services;

use App\Models\EquityInvestment;
use App\Models\Loan;
use App\Models\LoanOffer;
use App\Models\User;
use App\Repositories\EquityRoundRepository;
use App\Repositories\LoanOfferRepository;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvestmentService
{
    public function __construct(
        protected EquityRoundRepository $equityRoundRepository,
        protected LoanOfferRepository $loanOfferRepository,
    ) {
    }

    /**
     * Create an equity investment.
     *
     * IMPORTANT:
     *
     * This method does NOT:
     * - create a payment
     * - initialize Paystack
     * - reduce available_units
     *
     * It only creates the investment and stores the
     * temporary reservation in the user's session.
     */
    public function createEquityInvestment(
        User $user,
        array $payload
    ): EquityInvestment {
        if ($user->kyc_status !== 'verified') {
            throw new InvalidArgumentException(
                'Complete KYC before making a transaction.'
            );
        }

        
        return DB::transaction(function () use (
            $user,
            $payload
        ) {
            /*
             * Find the equity round.
             *
             * We do NOT lock it here because we are not
             * changing available_units.
             */
            $round = $this->equityRoundRepository->findById(
                (int) $payload['equity_round_id']
            );

            if (! $round) {
                throw new InvalidArgumentException(
                    'Equity round not found.'
                );
            }

            /*
             * Check round status.
             */
            $product = $round->product;

            if (
                ! $product ||
                ! in_array($product->instrument_type, ['equity', 'both'], true)
            ) {
                throw new InvalidArgumentException(
                    'This product does not support equity investments.'
                );
            }
            if ($round->status !== 'open') {
                throw new InvalidArgumentException(
                    'This equity round is not currently open.'
                );
            }

            /*
             * Check opening time.
             */
            if (
                $round->opens_at &&
                now()->lt($round->opens_at)
            ) {
                throw new InvalidArgumentException(
                    'This equity round has not opened yet.'
                );
            }

            /*
             * Check closing time.
             */
            if (
                $round->closes_at &&
                now()->gt($round->closes_at)
            ) {
                throw new InvalidArgumentException(
                    'This equity round has already closed.'
                );
            }

            /*
             * Convert amount to BigDecimal.
             */
            $amount = BigDecimal::of(
                (string) $payload['amount']
            );

            /*
             * Minimum ticket.
             */
            $minimumTicket = BigDecimal::of(
                (string) $round->minimum_ticket
            );

            if ($amount->isLessThan($minimumTicket)) {
                throw new InvalidArgumentException(
                    'Investment amount is below the round minimum ticket.'
                );
            }

            /*
             * Maximum ticket.
             */
            if (
                $round->maximum_ticket !== null &&
                $amount->isGreaterThan(
                    BigDecimal::of(
                        (string) $round->maximum_ticket
                    )
                )
            ) {
                throw new InvalidArgumentException(
                    'Investment amount exceeds the round maximum ticket.'
                );
            }

            /*
             * Price per unit.
             */
            $pricePerUnit = BigDecimal::of(
                (string) $round->price_per_unit
            );

            if ($pricePerUnit->isNegativeOrZero()) {
                throw new InvalidArgumentException(
                    'The equity round has an invalid unit price.'
                );
            }

            /*
             * Calculate units.
             *
             * Example:
             *
             * amount = 10,000
             * price  = 100
             *
             * units = 100
             */
            $units = $amount
                ->dividedBy($pricePerUnit, 6)
                ->toScale(6);

            if ($units->isNegativeOrZero()) {
                throw new InvalidArgumentException(
                    'The calculated investment units must be greater than zero.'
                );
            }

            /*
             * We are NOT deducting available_units here.
             *
             * We only make sure that the round currently
             * has enough units.
             */
            $availableUnits = BigDecimal::of(
                (string) $round->available_units
            );

            if ($units->isGreaterThan($availableUnits)) {
                throw new InvalidArgumentException(
                    'There are not enough units available for this investment.'
                );
            }

            /*
             * Create the investment.
             *
             * It remains pending until payment succeeds.
             */
           $investment = EquityInvestment::firstOrCreate(
                [
                    'investor_id' => $user->id,
                    'equity_round_id' => $round->id,
                ],
                [
                    'amount' => $amount->toScale(2)->__toString(),
                    'units' => $units->__toString(),
                    'status' => 'pending_payment',
                    'payment_reference' => null,
                    'agreement_id' => null,
                ]
            );

            /*
             * Store temporary reservation in the session.
             *
             * IMPORTANT:
             *
             * This does NOT change equity_rounds.available_units.
             */
            session()->put(
                "investment_reservation.{$investment->id}",
                [
                    'investment_id' => $investment->id,
                    'equity_round_id' => $round->id,
                    'user_id' => $user->id,
                    'amount' => $amount
                        ->toScale(2)
                        ->__toString(),
                    'units' => $units->__toString(),

                    /*
                     * 30-minute temporary checkout window.
                     */
                    'expires_at' => now()
                        ->addMinutes(30)
                        ->toIso8601String(),
                ]
            );
       

            return $investment;
        });
    }

    /**
     * Existing loan application.
     */
 
/**
 * Create a loan application after validating the loan offer.
 */
    public function createLoanApplication(
        User $user,
        array $payload
    ): Loan {
        if ($user->kyc_status !== 'verified') {
            throw new InvalidArgumentException(
                'Complete KYC before making a transaction.'
            );
        }

        return DB::transaction(function () use ($user, $payload) {
            /*
            * Lock the offer while checking its status and dates.
            * This prevents conflicting changes during this transaction.
            */
            $offer = LoanOffer::query()
                ->lockForUpdate()
                ->find((int) $payload['loan_offer_id']);

            if (! $offer) {
                throw new InvalidArgumentException(
                    'Loan offer not found.'
                );
            }

            if ($offer->status !== 'open') {
                throw new InvalidArgumentException(
                    'This loan offer is not currently open.'
                );
            }

            if (
                $offer->opened_at !== null &&
                now()->lt($offer->opened_at)
            ) {
                throw new InvalidArgumentException(
                    'This loan offer has not opened yet.'
                );
            }

            if (
                $offer->closed_at !== null &&
                now()->gt($offer->closed_at)
            ) {
                throw new InvalidArgumentException(
                    'This loan offer has already closed.'
                );
            }

            
            $offer->loadMissing('product');

            $product = $offer->product;

            if (
                ! $product ||
                ! in_array($product->instrument_type, ['loan', 'both'], true)
            ) {
                throw new InvalidArgumentException(
                    'This product does not support loan applications.'
                );
            }
            /*
            * Use BigDecimal instead of float for money.
            */
            $principal = BigDecimal::of(
                (string) $payload['principal_amount']
            );

            $minimum = BigDecimal::of(
                (string) $offer->minimum_amount
            );

            if ($principal->isNegativeOrZero()) {
                throw new InvalidArgumentException(
                    'Principal amount must be greater than zero.'
                );
            }

            if ($principal->isLessThan($minimum)) {
                throw new InvalidArgumentException(
                    'Principal amount is below the minimum allowed for this loan offer.'
                );
            }

            if ($offer->maximum_amount !== null) {
                $maximum = BigDecimal::of(
                    (string) $offer->maximum_amount
                );

                if ($maximum->isGreaterThanOrEqualTo($minimum)
                    && $principal->isGreaterThan($maximum)) {
                    throw new InvalidArgumentException(
                        'Principal amount exceeds the maximum allowed for this loan offer.'
                    );
                }
            }

            /*
            * Save the application.
            * Approval and disbursement are separate stages.
            */
            return Loan::create([
                'loan_offer_id' => $offer->id,
                'investor_id' => $user->id,
                'principal_amount' => $principal
                    ->toScale(2)
                    ->__toString(),
                'interest_rate' => $offer->interest_rate,
                'term_months' => $offer->term_months,
                'status' => 'pending_funding',
                'funded_at' => null,
                'maturity_date' => null,
                'agreement_id' => null,
                'payment_reference' => null,
            ]);
        });
    }

}