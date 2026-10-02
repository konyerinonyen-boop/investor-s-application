<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'loan_offer_id',
        'investor_id',
        'principal_amount',
        'interest_rate',
        'term_months',
        'status',
        'funded_at',
        'maturity_date',
        'agreement_id',
        'payment_reference',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'funded_at' => 'datetime',
        'maturity_date' => 'datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(LoanOffer::class, 'loan_offer_id');
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(RepaymentSchedule::class);
    }
}
