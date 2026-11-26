<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DividendAllocation extends Model
{
    protected $fillable = [
        'dividend_declaration_id', 'investor_id', 'eligible_units', 'amount',
        'status', 'wallet_transaction_id', 'payment_reference', 'paid_at',
        'failure_reason', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'eligible_units' => 'decimal:6',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(DividendDeclaration::class, 'dividend_declaration_id');
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id');
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }
}
