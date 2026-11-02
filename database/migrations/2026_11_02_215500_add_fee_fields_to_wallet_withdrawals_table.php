<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_withdrawals', function (Blueprint $table) {
            $table->decimal('fee', 15, 2)->default(0)->after('amount');
            $table->decimal('total_debit', 15, 2)->default(0)->after('fee');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_withdrawals', function (Blueprint $table) {
            $table->dropColumn(['fee', 'total_debit']);
        });
    }
};