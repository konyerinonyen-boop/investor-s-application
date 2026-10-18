
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_withdrawals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('wallet_transaction_id')
                ->nullable()
                ->constrained('wallet_transactions')
                ->nullOnDelete();

            // Amount reserved from the investor's wallet.
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN');

            // Snapshot of the destination bank account.
            $table->string('bank_name');
            $table->string('bank_code', 20);
            $table->string('account_number', 20);
            $table->string('account_name');

            // requested, approved, processing, paid, rejected, failed
            $table->string('status', 30)->default('requested');

            // Paystack transfer details.
            $table->string('recipient_code')->nullable();
            $table->string('transfer_reference')->nullable()->unique();

            $table->json('provider_response')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_withdrawals');
    }
};
