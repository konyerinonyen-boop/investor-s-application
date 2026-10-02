<?php

namespace Tests\Feature;

use App\Models\EquityRound;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvestorFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_investor_can_register_and_login(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Alice Investor',
            'email' => 'alice@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'alice@example.com');

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'alice@example.com',
            'password' => 'secret123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'alice@example.com')
            ->assertJsonPath('data.token', fn ($token) => is_string($token) && $token !== '');
    }

    public function test_investor_requires_kyc_for_equity_commitment(): void
    {
        $user = User::create([
            'name' => 'Bob Investor',
            'email' => 'bob@example.com',
            'password' => Hash::make('secret123'),
            'kyc_status' => 'not_started',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'FadaWebs Growth Fund',
            'slug' => 'fadawebs-growth-fund',
            'description' => 'Equity vehicle',
            'instrument_type' => 'equity',
            'status' => 'active',
            'minimum_investment' => '500.00',
            'maximum_investment' => '50000.00',
        ]);

        $round = EquityRound::create([
            'product_id' => $product->id,
            'name' => 'Seed Round',
            'status' => 'open',
            'valuation' => '2500000.00',
            'price_per_unit' => '10.00',
            'total_units' => 2000,
            'available_units' => 2000,
            'minimum_ticket' => '500.00',
            'maximum_ticket' => '10000.00',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/investments/equity', [
            'equity_round_id' => $round->id,
            'amount' => 1000,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Complete KYC before making a transaction.');
    }

    public function test_verified_investor_can_commit_equity(): void
    {
        $product = Product::create([
            'name' => 'FadaWebs Growth Fund',
            'slug' => 'fadawebs-growth-fund-2',
            'description' => 'Equity vehicle',
            'instrument_type' => 'equity',
            'status' => 'active',
            'minimum_investment' => '500.00',
            'maximum_investment' => '50000.00',
        ]);

        $round = EquityRound::create([
            'product_id' => $product->id,
            'name' => 'Expansion Round',
            'status' => 'open',
            'valuation' => '5000000.00',
            'price_per_unit' => '25.00',
            'total_units' => 2500,
            'available_units' => 2500,
            'minimum_ticket' => '500.00',
            'maximum_ticket' => '15000.00',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(45),
        ]);

        $user = User::create([
            'name' => 'Clara Investor',
            'email' => 'clara@example.com',
            'password' => Hash::make('secret123'),
            'kyc_status' => 'verified',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/investments/equity', [
            'equity_round_id' => $round->id,
            'amount' => 1500,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.investment.amount', '1500.00');

        $round->refresh();
        $this->assertSame(2500 - 60, $round->available_units);
    }
}
