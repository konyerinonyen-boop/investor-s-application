<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquityOwnershipTransaction extends Model
{
    protected $fillable = [
        'investor_id',
        'equity_round_id',
        'equity_investment_id',
        'counterparty_investor_id',
        'transaction_type',
        'units_delta',
        'unit_price',
        'total_amount',
        'currency',
        'reference',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'units_delta' => 'decimal:6',
        'unit_price' => 'decimal:6',
        'total_amount' => 'decimal:2',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(EquityRound::class, 'equity_round_id');
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(
            EquityInvestment::class,
            'equity_investment_id'
        );
    }

    public function counterpartyInvestor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'counterparty_investor_id'
        );
    }
}