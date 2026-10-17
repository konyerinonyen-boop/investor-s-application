<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            // Each user can have only one wallet.
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();

            // All wallet balances are denominated in naira.
            $table->string('currency', 3)->default('NGN');

            // Available balance, initially zero.
            $table->decimal('balance', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
