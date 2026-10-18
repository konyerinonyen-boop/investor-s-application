
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletWithdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_transaction_id',
        'amount',
        'currency',
        'bank_name',
        'bank_code',
        'account_number',
        'account_name',
        'status',
        'recipient_code',
        'transfer_reference',
        'provider_response',
        'failure_reason',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'provider_response' => 'array',
            'transferred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransaction::class,
            'wallet_transaction_id'
        );
    }
}
