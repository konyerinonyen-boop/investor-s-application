<?php

namespace Tests\Unit;

use App\Actions\GenerateRepaymentSchedule;
use App\Models\EquityRound;
use App\Models\Loan;
use App\Models\LoanOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_equity_round_tracks_units_and_capacity(): void
    {
        $round = EquityRound::create([
            'name' => 'Seed Round',
            'status' => 'open',
            'valuation' => '2500000.00',
            'price_per_unit' => '10.00',
            'total_units' => 25000,
            'available_units' => 25000,
            'minimum_ticket' => '100.00',
            'maximum_ticket' => '5000.00',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(30),
        ]);

        $this->assertSame(25000, $round->available_units);
        $this->assertSame(25000, $round->remaining_units);
    }

    public function test_loan_offer_generates_a_valid_repayment_schedule(): void
    {
        $offer = LoanOffer::create([
            'name' => 'Bridge Loan',
            'status' => 'active',
            'product_id' => 1,
            'interest_rate' => '14.50',
            'term_months' => 12,
            'minimum_amount' => '1000.00',
            'maximum_amount' => '50000.00',
        ]);

        $loan = Loan::create([
            'loan_offer_id' => $offer->id,
            'investor_id' => 1,
            'principal_amount' => '10000.00',
            'interest_rate' => '14.50',
            'term_months' => 12,
            'status' => 'active',
            'funded_at' => now(),
        ]);

        $schedule = app(GenerateRepaymentSchedule::class)->handle($loan, 12, 'monthly');

        $this->assertCount(12, $schedule);
        $this->assertSame('monthly', $schedule->first()->frequency);
        $this->assertGreaterThan(0, (float) $schedule->first()->amount);
    }
}
