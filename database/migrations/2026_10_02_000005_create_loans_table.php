<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_offer_id')->nullable();
            $table->unsignedBigInteger('investor_id')->nullable();
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_rate', 8, 4)->default(0);
            $table->unsignedInteger('term_months')->default(12);
            $table->string('status')->default('pending_funding');
            $table->timestamp('funded_at')->nullable();
            $table->timestamp('maturity_date')->nullable();
            $table->unsignedBigInteger('agreement_id')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
