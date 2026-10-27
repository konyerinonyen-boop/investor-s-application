<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycProfile extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'bank_name',
        'bank_code',
        'account_number',
        'account_name',
        'trigger_source',
        'rejection_reason',
        'submitted_ip',
        'identity_verification_status',
        'identity_verified_at',
        'bank_verification_status',
        'bank_verification_reference',
        'bank_verified_at',
        'sumsub_applicant_id',
    ];

    protected function casts(): array
    {
        return [
            'identity_verified_at' => 'datetime',
            'bank_verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}