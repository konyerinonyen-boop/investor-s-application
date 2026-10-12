<?php

namespace App\Models;

use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RepaymentSchedule extends Model
{
    protected $fillable = [
        'loan_id',
        'due_date',
        'amount',
        'principal_amount',
        'interest_amount',
        'frequency',
        'status',
        'paid_at',
        'payment_reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payment(): HasOne
{
    return $this->hasOne(
        Payment::class,
        'repayment_schedule_id'
    );
}
}
