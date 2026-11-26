<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DividendDeclaration extends Model
{
    protected $fillable = [
        'equity_round_id', 'created_by', 'declared_by', 'approved_by',
        'title', 'currency', 'total_amount', 'record_date', 'status',
        'total_eligible_units', 'total_allocated', 'declared_at',
        'approved_at', 'payment_started_at', 'paid_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'total_eligible_units' => 'decimal:6',
            'total_allocated' => 'decimal:2',
            'record_date' => 'date',
            'declared_at' => 'datetime',
            'approved_at' => 'datetime',
            'payment_started_at' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(EquityRound::class, 'equity_round_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(DividendAllocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function declarer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
