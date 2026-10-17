
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')
                ->constrained()
                ->restrictOnDelete();

            // Examples: deposit, investment, loan_funding, repayment.
            $table->string('type', 40);

            // A transaction either adds money or deducts money.
            $table->string('direction', 10);

            // Store positive amounts; direction determines the effect.
            $table->decimal('amount', 15, 2);

            // Wallet balance immediately after this transaction.
            $table->decimal('balance_after', 15, 2);

            // Unique key prevents the same financial event being posted twice.
            $table->string('reference', 100)->unique();

            $table->string('description')->nullable();

            // Optional links to the source record or provider details.
            $table->nullableMorphs('source');
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['type', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
