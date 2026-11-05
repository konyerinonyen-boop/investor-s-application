<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('equity_marketplace_trades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('listing_id')
                ->constrained('equity_listings')
                ->restrictOnDelete();

            $table->foreignId('seller_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('buyer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('equity_round_id')
                ->constrained('equity_rounds')
                ->restrictOnDelete();

            $table->decimal('units', 18, 6);
            $table->decimal('unit_price', 18, 6);
            $table->decimal('total_amount', 18, 2);

            $table->string('currency', 3)->default('NGN');

            $table->string('status', 30)->default('completed');

            $table->string('reference')->unique();

            $table->timestamp('executed_at');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['buyer_id', 'executed_at']);
            $table->index(['seller_id', 'executed_at']);
            $table->index(['listing_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equity_marketplace_trades');
    }
};
