<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquityRound extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'status',
        'valuation',
        'price_per_unit',
        'total_units',
        'available_units',
        'minimum_ticket',
        'maximum_ticket',
        'opens_at',
        'closes_at',
    ];

    protected $casts = [
        'valuation' => 'decimal:2',
        'price_per_unit' => 'decimal:2',
        'minimum_ticket' => 'decimal:2',
        'maximum_ticket' => 'decimal:2',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(EquityInvestment::class);
    }

    public function getRemainingUnitsAttribute(): int
    {
        return (int) $this->available_units;
    }
}
