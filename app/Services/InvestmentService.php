<?php

namespace App\Services;

use App\Models\EquityInvestment;
use App\Models\Loan;
use App\Models\User;
use App\Repositories\EquityRoundRepository;
use App\Repositories\LoanOfferRepository;
use InvalidArgumentException;

class InvestmentService
{
    public function __construct(
        protected EquityRoundRepository $equityRoundRepository,
        protected LoanOfferRepository $loanOfferRepository,
    ) {
    }

    public function createEquityInvestment(User $user, array $payload): EquityInvestment
    {
        if ($user->kyc_status !== 'verified') {
            throw new InvalidArgumentException('Complete KYC before making a transaction.');
        }

        $round = $this->equityRoundRepository->findById((int) $payload['equity_round_id']);

        if (! $round) {
            throw new InvalidArgumentException('Equity round not found.');
        }

        $amount = (float) $payload['amount'];
        $units = $amount / (float) $round->price_per_unit;

        if ($amount < (float) $round->minimum_ticket) {
            throw new InvalidArgumentException('Investment amount is below the round minimum ticket.');
        }

        if ($round->maximum_ticket && $amount > (float) $round->maximum_ticket) {
            throw new InvalidArgumentException('Investment amount exceeds the round maximum ticket.');
        }

        $this->equityRoundRepository->reserveUnits($round, $units);

        return EquityInvestment::create([
            'investor_id' => $user->id,
            'equity_round_id' => $round->id,
            'amount' => $amount,
            'units' => $units,
            'status' => 'pending',
            'payment_reference' => null,
            'agreement_id' => null,
        ]);
    }

    public function createLoanApplication(User $user, array $payload): Loan
    {
        if ($user->kyc_status !== 'verified') {
            throw new InvalidArgumentException('Complete KYC before making a transaction.');
        }

        $offer = $this->loanOfferRepository->findById((int) $payload['loan_offer_id']);

        if (! $offer) {
            throw new InvalidArgumentException('Loan offer not found.');
        }

        $principal = (float) $payload['principal_amount'];

        if ($principal < (float) $offer->minimum_amount) {
            throw new InvalidArgumentException('Principal amount is below the minimum allowed for this loan offer.');
        }

        if ($offer->maximum_amount && $principal > (float) $offer->maximum_amount) {
            throw new InvalidArgumentException('Principal amount exceeds the maximum allowed for this loan offer.');
        }

        return Loan::create([
            'loan_offer_id' => $offer->id,
            'investor_id' => $user->id,
            'principal_amount' => $principal,
            'interest_rate' => $offer->interest_rate,
            'term_months' => $offer->term_months,
            'status' => 'pending_funding',
            'funded_at' => null,
            'maturity_date' => null,
            'agreement_id' => null,
            'payment_reference' => null,
        ]);
    }
}
