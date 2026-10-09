<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equity_rounds', function (Blueprint $table) {
            $table->decimal('total_units', 18, 6)
                ->default(0)
                ->change();

            $table->decimal('available_units', 18, 6)
                ->default(0)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('equity_rounds', function (Blueprint $table) {
            $table->unsignedBigInteger('total_units')
                ->default(0)
                ->change();

            $table->unsignedBigInteger('available_units')
                ->default(0)
                ->change();
        });
    }
};