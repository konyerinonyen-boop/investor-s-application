<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'instrument_type',
        'status',
        'minimum_investment',
        'maximum_investment',
        'interest_rate',
        'launch_date',
    ];

    protected $casts = [
        'minimum_investment' => 'decimal:2',
        'maximum_investment' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'launch_date' => 'datetime',
    ];

    public function equityRounds(): HasMany
    {
        return $this->hasMany(EquityRound::class);
    }

    public function loanOffers(): HasMany
    {
        return $this->hasMany(LoanOffer::class);
    }
}
