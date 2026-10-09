<?php

namespace App\Models;

use App\Models\EquityRound;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquityInvestment extends Model
{
    protected $fillable = [
        'investor_id',
        'equity_round_id',
        'amount',
        'units',
        'status',
        'payment_reference',
        'agreement_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'units' => 'decimal:6',
    ];

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(EquityRound::class, 'equity_round_id');
    }

        public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'investment_id');
    }

}
