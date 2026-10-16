<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repayment_payouts', function (Blueprint $table) {
            $table->id();

            // One payout review record per repayment installment.
            $table->foreignId('repayment_schedule_id')
                ->unique()
                ->constrained('repayment_schedules')
                ->cascadeOnDelete();

            // The intended recipient. We will populate this only
            // after confirming the actual investor-funding relationship.
            $table->foreignId('investor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN');

            // Admin approval workflow.
            $table->string('approval_status')
                ->default('pending_review');

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // Paystack transfer workflow.
            $table->string('recipient_code')->nullable();
            $table->string('transfer_reference')->nullable()->unique();

            $table->string('transfer_status')
                ->default('not_started');

            $table->json('provider_response')->nullable();
            $table->timestamp('transferred_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repayment_payouts');
    }
};