<?php

namespace App\Models;

use \Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use App\Models\Document;
use App\Models\EquityInvestment;
use App\Models\KycProfile;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Wallet;
use App\Notifications\VerifyEmailNotification;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail, HasName
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, MustVerifyEmailTrait;

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

public function getFilamentName(): string
{
    return trim($this->first_name . ' ' . $this->last_name);
}

public function getFullNameAttribute(): string
{
    return trim($this->first_name .' '. $this->last_name);
}
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
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
        return $this->hasMany(Payment::class,'');
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
}
