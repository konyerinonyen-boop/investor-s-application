<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dividend_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equity_round_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('declared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('currency', 3)->default('NGN');
            $table->decimal('total_amount', 15, 2);
            $table->date('record_date');
            $table->string('status', 32)->default('draft');
            $table->decimal('total_eligible_units', 18, 6)->default(0);
            $table->decimal('total_allocated', 15, 2)->default(0);
            $table->timestamp('declared_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('payment_started_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['equity_round_id', 'status']);
            $table->index('record_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dividend_declarations');
    }
};
