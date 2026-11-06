<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquityListing extends Model
{
    protected $fillable = [
        'seller_id',
        'equity_round_id',
        'units_listed',
        'units_remaining',
        'unit_price',
        'status',
        'reference',
        'expires_at',
    ];

    protected $casts = [
        'units_listed' => 'decimal:6',
        'units_remaining' => 'decimal:6',
        'unit_price' => 'decimal:6',
        'expires_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(EquityRound::class, 'equity_round_id');
    }

    public function trades(): HasMany
    {
        return $this->hasMany(EquityMarketplaceTrade::class, 'listing_id');
    }
}
