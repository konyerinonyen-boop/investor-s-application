
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            // Remove manual-review-only columns.
            $table->dropColumn([
                'reviewed_by',
                'reviewed_at',
            ]);

            // Add fields for automated BVN/NIN verification.
            $table->string('identity_type', 10)->nullable();
            $table->text('identity_number')->nullable();

            // Store the outcome of the automated verification.
            $table->string('verification_reference')->nullable();
            $table->timestamp('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            // Remove the new Dojah fields.
            $table->dropColumn([
                'identity_type',
                'identity_number',
                'verification_reference',
                'verified_at',
            ]);

            // Restore the manual-review columns.
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
        });
    }
};
