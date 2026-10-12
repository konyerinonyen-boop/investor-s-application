<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'investment_id',
        'loan_id',
        'repayment_schedule_id',
        'amount',
        'currency',
        'provider',
        'provider_reference',
        'checkout_expires_at',
        'status',
        'payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'checkout_expires_at' => 'datetime',
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(
            EquityInvestment::class,
            'investment_id'
        );
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function repaymentSchedule(): BelongsTo
    {
        return $this->belongsTo(
            RepaymentSchedule::class,
            'repayment_schedule_id'
        );
    }
}
