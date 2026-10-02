<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('instrument_type')->default('equity');
            $table->string('status')->default('draft');
            $table->decimal('minimum_investment', 15, 2)->default(0);
            $table->decimal('maximum_investment', 15, 2)->nullable();
            $table->decimal('interest_rate', 8, 4)->default(0);
            $table->timestamp('launch_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
