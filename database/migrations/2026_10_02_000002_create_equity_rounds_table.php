<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equity_rounds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name');
            $table->string('status')->default('open');
            $table->decimal('valuation', 15, 2)->default(0);
            $table->decimal('price_per_unit', 15, 2)->default(0);
            $table->unsignedBigInteger('total_units')->default(0);
            $table->unsignedBigInteger('available_units')->default(0);
            $table->decimal('minimum_ticket', 15, 2)->default(0);
            $table->decimal('maximum_ticket', 15, 2)->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equity_rounds');
    }
};
