<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {

          if (Schema::hasColumns('kyc_profiles', [
                'routing_code',
                'review_notes',
                'document_path',
               ])){
            $table->dropColumn('routing_code');
            $table->dropColumn('review_notes');
            $table->dropColumn('document_path');
               }
               
               $table->enum('trigger_source', [
                'first_investment','first_lend','resubmission',
            ]);
            // Lifecycle (B1 flow)
            $table->enum('status', [
                'pending',
                'verified',
                'rejected',
            ])->default('pending')->change();
            
            // Identity documents (A1b step 2)
            $table->string('id_document_type')->nullable(); // 'nin', 'passport', 'drivers_license'
            $table->string('id_document_number')->nullable();
            $table->string('id_document_path_front')->nullable(); // storage path, encrypted at rest
            $table->string('id_document_path_back')->nullable(); // storage path, encrypted at rest

             // Admin review (B1)

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

             $table->ipAddress('submitted_ip')->nullable();

        });

        Schema::table('users', function (Blueprint $table) {
            //drop the columns first
            //  $table->dropColumn('name');
            //  $table->dropColumn('is_verified');

        //before editing the table, we need to add the new columns
             $table->enum('account_type', ['individual', 'entity'])->default('individual');
             $table->string('first_name');
             $table->string('last_name');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
               if (Schema::hasColumns('kyc_profiles', [
                'trigger_source',
                'id_document_type',
                'id_document_number',
                'id_document_path_front',
                'reviewed_by',
                'reviewed_at',
                'rejection_reason',
                'submitted_ip',
               ])) {
           $table->dropColumn('trigger_source');
            $table->dropColumn('id_document_type');
            $table->dropColumn('id_document_number');
            $table->dropColumn('id_document_path_front');
            $table->dropColumn('id_document_path_back');
            
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn('reviewed_by');

            $table->dropColumn('reviewed_at'); 
            $table->dropColumn('rejection_reason'); 
            $table->dropColumn('submitted_ip'); 
               }    
            $table->string('status')->default('not_started')->change();
             $table->string('document_path')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('routing_code')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumns('user',[
               'account_type', 
               'first_name', 
               'last_name', 
            ])) {
             $table->dropColumn('account_type');
            $table->dropColumn('first_name');
            $table->dropColumn('last_name');
            }
            $table->string('name');
            $table->boolean('is_verified')->default(false);
        });
    }
};
