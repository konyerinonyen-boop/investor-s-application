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
        Schema::table('equity_ownership_transactions', function (Blueprint $table) {
            $table->foreignId('marketplace_trade_id')
                ->nullable()
                ->after('equity_investment_id')
                ->constrained('equity_marketplace_trades')
                ->restrictOnDelete();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('equity_ownership_transactions', function (Blueprint $table) {
        $table->dropConstrainedForeignId('marketplace_trade_id');
    });
}

};
