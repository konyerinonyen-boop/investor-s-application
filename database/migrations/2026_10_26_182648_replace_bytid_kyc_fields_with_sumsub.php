<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            if (Schema::hasColumn(
                'kyc_profiles',
                'bytid_document_verification_id'
            )) {
                $table->dropIndex(
                    'kyc_profiles_bytid_document_verification_id_index'
                );

                $table->dropColumn('bytid_document_verification_id');
            }

            if (! Schema::hasColumn('kyc_profiles', 'sumsub_applicant_id')) {
                $table->string('sumsub_applicant_id')
                    ->nullable()
                    ->index();
            }
        });

        // Remove obsolete provider-specific event tables.
        Schema::dropIfExists('bytid_webhook_events');
        Schema::dropIfExists('sumsub_webhook_events');
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('kyc_profiles', 'sumsub_applicant_id')) {
                $table->dropIndex('kyc_profiles_sumsub_applicant_id_index');
                $table->dropColumn('sumsub_applicant_id');
            }

            if (! Schema::hasColumn(
                'kyc_profiles',
                'bytid_document_verification_id'
            )) {
                $table->string('bytid_document_verification_id')
                    ->nullable()
                    ->index();
            }
        });
    }
};
