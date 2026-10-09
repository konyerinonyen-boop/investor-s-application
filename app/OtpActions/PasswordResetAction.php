<?php

namespace App\OtpActions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;

class PasswordResetAction implements VerifiableAction
{
    public function __construct(public string $email, public string $passwordHash)
    {
    }

    public function handle(): mixed
    {
        $user = User::where('email', $this->email)->firstOrFail();
        $user->update(['password' => Hash::make($this->passwordHash)]);

        return $user;
    }
}

