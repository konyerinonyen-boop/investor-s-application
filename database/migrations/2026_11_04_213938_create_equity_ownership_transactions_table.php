<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equity_ownership_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('investor_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('equity_round_id')
                ->constrained('equity_rounds')
                ->restrictOnDelete();

            $table->foreignId('equity_investment_id')
                ->nullable()
                ->constrained('equity_investments')
                ->restrictOnDelete();

            $table->foreignId('counterparty_investor_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('transaction_type', 50);

            // Positive units mean acquired; negative units mean disposed.
            $table->decimal('units_delta', 18, 6);

            // Price for one unit.
            $table->decimal('unit_price', 18, 6);

            // Total transaction value in the stated currency.
            $table->decimal('total_amount', 18, 2);

            $table->string('currency', 3)->default('NGN');

            // Unique reference makes retries and backfills safe.
            $table->string('reference')->unique();

            $table->timestamp('occurred_at');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(
                ['investor_id', 'equity_round_id', 'occurred_at'],
                'equity_ownership_history_index'
            );

            $table->index('transaction_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equity_ownership_transactions');
    }
};