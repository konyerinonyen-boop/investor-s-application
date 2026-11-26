<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dividend_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dividend_declaration_id')->constrained()->restrictOnDelete();
            $table->foreignId('investor_id')->constrained('users')->restrictOnDelete();
            $table->decimal('eligible_units', 18, 6);
            $table->decimal('amount', 15, 2);
            $table->string('status', 24)->default('pending');
            $table->foreignId('wallet_transaction_id')->nullable()
                ->constrained('wallet_transactions')->restrictOnDelete();
            $table->string('payment_reference', 100)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['dividend_declaration_id', 'investor_id']);
            $table->index(['dividend_declaration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dividend_allocations');
    }
};
