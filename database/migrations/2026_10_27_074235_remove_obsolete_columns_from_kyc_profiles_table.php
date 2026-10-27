<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'kyc_profiles';

        if (! Schema::hasTable($table)) {
            return;
        }

        // Columns no longer needed because Sumsub handles identity
        // document collection, selfie checks, and identity verification.
        $columnsToRemove = [
            'bytid_document_verification_id',
            'id_document_path_front',
            'id_document_path_back',
            'selfie_path',
            'id_document_type',
            'id_document_number',
            'identity_type',
            'identity_number',
            'identity_verification_reference',
            'selfie_verification_reference',
            'selfie_verification_status',
            'selfie_verified_at',
            'reviewed_by',
            'reviewed_at',
        ];

        $existingColumns = array_values(array_filter(
            $columnsToRemove,
            fn (string $column): bool => Schema::hasColumn($table, $column)
        ));

        if ($existingColumns === []) {
            return;
        }

        // Remove foreign-key constraints attached to obsolete columns first.
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (array_intersect(
                $foreignKey['columns'],
                $existingColumns
            )) {
                Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
                    $blueprint->dropForeign($foreignKey['name']);
                });
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($existingColumns) {
            $blueprint->dropColumn($existingColumns);
        });
    }

    public function down(): void
    {
        // Removed column data cannot be recovered by rolling back.
        // Restore the database from a backup if that data is needed.
    }
};