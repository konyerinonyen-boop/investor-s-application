<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Equity Rounds
        |--------------------------------------------------------------------------
        | Do not allow a product to be deleted while an equity round
        | is still referencing it.
        */
        Schema::table('equity_rounds', function (Blueprint $table) {

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        Schema::table('payments', function (Blueprint $table) {
            /*
             * This FK already exists in the original migration
             * with cascadeOnDelete().
             *
             * Replace it with restrictOnDelete().
             */
            $table->dropForeign(['user_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            /*
             * These two FKs DO NOT exist in the original payments
             * migration, so we ADD them instead of dropping them.
             */
            $table->foreign('investment_id')
                ->references('id')
                ->on('equity_investments')
                ->restrictOnDelete();

            $table->foreign('loan_id')
                ->references('id')
                ->on('loans')
                ->restrictOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | Loans
        |--------------------------------------------------------------------------
        | Loans are financial records, so their investor and loan offer
        | must not be deleted underneath them.
        */
        Schema::table('loans', function (Blueprint $table) {
            $table->foreign('loan_offer_id')
                ->references('id')
                ->on('loan_offers')
                ->restrictOnDelete();

            $table->foreign('investor_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Equity Rounds
        |--------------------------------------------------------------------------
        */
        Schema::table('equity_rounds', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
              $table->unsignedBigInteger('product_id')->nullable();
        });
        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */
        Schema::table('payments', function (Blueprint $table) {
            /*
             * Remove the new investment/loan relationships.
             */
            $table->dropForeign(['investment_id']);
            $table->dropForeign(['loan_id']);

                  $table->unsignedBigInteger('investment_id')->nullable();
                    $table->unsignedBigInteger('loan_id')->nullable();
            /*
             * Restore the original user FK behavior.
             */
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        });
        /*
        |--------------------------------------------------------------------------
        | Loans
        |--------------------------------------------------------------------------
        */
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['loan_offer_id']);
            $table->dropForeign(['investor_id']);

            $table->id();
            $table->unsignedBigInteger('loan_offer_id')->nullable();

              $table->unsignedBigInteger('investor_id')->nullable();
        });

    }
};