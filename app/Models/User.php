<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'status',
        'kyc_status',
        'country',
        'account_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if (empty($user->name) && ($user->first_name || $user->last_name)) {
                $user->name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            }
        });
    }

    public function getFilamentName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->can('access admin panel');
    }

    public function kycProfile()
    {
        return $this->hasOne(KycProfile::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function investments()
    {
        return $this->hasMany(EquityInvestment::class, 'investor_id');
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'investor_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, '');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function walletTransactions()
    {
        return $this->hasManyThrough(
            WalletTransaction::class,
            Wallet::class
        );
    }

    public function equityHoldings(): HasMany
    {
        return $this->hasMany(EquityHolding::class, 'investor_id');
    }

    public function equityOwnershipTransactions(): HasMany
    {
        return $this->hasMany(
            EquityOwnershipTransaction::class,
            'investor_id'
        );
    }

    public function equityListings(): HasMany
    {
        return $this->hasMany(EquityListing::class, 'seller_id');
    }

    public function marketplaceSales(): HasMany
    {
        return $this->hasMany(
            EquityMarketplaceTrade::class,
            'seller_id'
        );
    }

    public function marketplacePurchases(): HasMany
    {
        return $this->hasMany(
            EquityMarketplaceTrade::class,
            'buyer_id'
        );
    }
}
