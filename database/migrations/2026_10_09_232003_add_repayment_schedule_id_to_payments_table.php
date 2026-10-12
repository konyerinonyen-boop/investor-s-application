<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('repayment_schedule_id')
                ->nullable()
                ->after('loan_id')
                ->constrained('repayment_schedules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['repayment_schedule_id']);
            $table->dropColumn('repayment_schedule_id');
        });
    }
};
