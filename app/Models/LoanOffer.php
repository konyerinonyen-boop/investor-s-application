<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanOffer extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'status',
        'interest_rate',
        'term_months',
        'minimum_amount',
        'maximum_amount',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'interest_rate' => 'decimal:4',
        'minimum_amount' => 'decimal:2',
        'maximum_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
