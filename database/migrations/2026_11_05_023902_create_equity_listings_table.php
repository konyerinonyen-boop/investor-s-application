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
        Schema::create('equity_listings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seller_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('equity_round_id')
                ->constrained('equity_rounds')
                ->restrictOnDelete();

            $table->decimal('units_listed', 18, 6);
            $table->decimal('units_remaining', 18, 6);

            $table->decimal('unit_price', 18, 6);

            $table->string('status', 30)->default('active');

            $table->string('reference')->unique();

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(
                ['equity_round_id', 'status'],
                'equity_listings_round_status_index'
            );

            $table->index(
                ['seller_id', 'status'],
                'equity_listings_seller_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equity_listings');
    }
};
