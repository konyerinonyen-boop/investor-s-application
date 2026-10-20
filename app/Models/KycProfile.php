<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycProfile extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'id_document_type',
        'id_document_number',
        'id_document_path_front',
        'id_document_path_back',
        'selfie_path',
        'bank_name',
        'bank_code',
        'account_number',
        'trigger_source',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'submitted_ip',
        'bank_code',
        'account_name',

        'identity_verification_status',
        'identity_verification_reference',
        'identity_verified_at',

        'selfie_verification_status',
        'selfie_verification_reference',
        'selfie_verified_at',

        'bank_verification_status',
        'bank_verification_reference',
        'bank_verified_at',
        'bytid_document_verification_id',
    ];
    protected function casts(): array
    {
        return [
            'identity_verified_at' => 'datetime',
            'selfie_verified_at' => 'datetime',
            'bank_verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function idDocumentFrontUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->id_document_path_front
                ? asset('storage/' . $this->id_document_path_front)
                : null
        );
    }

    protected function idDocumentBackUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->id_document_path_back
                ? asset('storage/' . $this->id_document_path_back)
                : null
        );
    }

    protected function selfieUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->selfie_path
                ? asset('storage/' . $this->selfie_path)
                : null
        );
    }
    
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
