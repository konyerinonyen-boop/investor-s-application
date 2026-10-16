<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepaymentPayout extends Model
{
    protected $fillable = [
        'repayment_schedule_id',
        'investor_id',
        'amount',
        'currency',
        'approval_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'recipient_code',
        'transfer_reference',
        'transfer_status',
        'provider_response',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'transferred_at' => 'datetime',
            'provider_response' => 'array',
        ];
    }

    public function repaymentSchedule(): BelongsTo
    {
        return $this->belongsTo(RepaymentSchedule::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}