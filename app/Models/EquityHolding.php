<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquityHolding extends Model
{
    protected $fillable = [
        'investor_id',
        'equity_round_id',
        'units',
    ];

    protected $casts = [
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
}