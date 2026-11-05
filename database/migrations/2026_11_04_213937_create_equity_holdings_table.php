<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equity_holdings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('investor_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('equity_round_id')
                ->constrained('equity_rounds')
                ->restrictOnDelete();

            $table->decimal('units', 18, 6)->default(0);

            $table->timestamps();

            $table->unique(
                ['investor_id', 'equity_round_id'],
                'equity_holdings_investor_round_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equity_holdings');
    }
};