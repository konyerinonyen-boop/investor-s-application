<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equity_rounds', function (Blueprint $table) {
            $table->decimal('price_per_unit', 18, 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('equity_rounds', function (Blueprint $table) {
            $table->decimal('price_per_unit', 15, 2)->change();
        });
    }
};