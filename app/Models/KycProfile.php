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
        'account_number',
        'trigger_source',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'submitted_ip',
    ];

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
