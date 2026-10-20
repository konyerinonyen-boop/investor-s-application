
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            // Identity document verification
            $table->string('identity_verification_status')
                ->default('pending');

            $table->string('identity_verification_reference')
                ->nullable();

            $table->timestamp('identity_verified_at')
                ->nullable();
    
            $table->string('bytid_document_verification_id')
                    ->nullable()
                    ->index();
            // Selfie verification
            $table->string('selfie_verification_status')
                ->default('pending');

            $table->string('selfie_verification_reference')
                ->nullable();

            $table->timestamp('selfie_verified_at')
                ->nullable();

            // Bank account verification
            $table->string('bank_verification_status')
                ->default('pending');

            $table->string('bank_verification_reference')
                ->nullable();

            $table->timestamp('bank_verified_at')
                ->nullable();

            // Verified bank account holder's name
            $table->string('account_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
               $table->dropIndex([
                'bytid_document_verification_id',
            ]);
            $table->dropColumn([
                'identity_verification_status',
                'identity_verification_reference',
                'identity_verified_at',
                'selfie_verification_status',
                'selfie_verification_reference',
                'selfie_verified_at',
                'bank_verification_status',
                'bank_verification_reference',
                'bank_verified_at',
                'account_name',
                'bytid_document_verification_id'
            ]);
        });
    }
};
