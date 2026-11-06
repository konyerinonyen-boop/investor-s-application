<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquityMarketplaceTrade extends Model
{
    protected $fillable = [
        'listing_id',
        'seller_id',
        'buyer_id',
        'equity_round_id',
        'units',
        'unit_price',
        'total_amount',
        'currency',
        'status',
        'reference',
        'executed_at',
        'metadata',
    ];

    protected $casts = [
        'units' => 'decimal:6',
        'unit_price' => 'decimal:6',
        'total_amount' => 'decimal:2',
        'executed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(EquityListing::class, 'listing_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(EquityRound::class, 'equity_round_id');
    }

    public function ownershipTransactions(): HasMany
    {
        return $this->hasMany(
            EquityOwnershipTransaction::class,
            'marketplace_trade_id'
        );
    }
}
