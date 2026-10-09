<?php

namespace App\Services;

use App\Models\User;
use App\OtpActions\PasswordResetAction;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Support\OtpStatus;

class AuthService
{
    public function __construct(protected UserRepository $userRepository)
    {
    }

    public function register(array $data): array
    {
     $user = DB::transaction(function () use ($data) {
        $user = $this->userRepository->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'kyc_status' => 'not_started',
            'status' => 'active',
            'country' => $data['country'],
            'account_type' => $data['account_type'],
            'phone' => $data['phone'],        
        ]);

        $this->userRepository->assignInvestorRole($user);
     $user->sendEmailVerificationNotification();
        
     return $user;
        });
        return [
            'user' => $user->fresh()->load('roles'),
            'token' => $user->createToken('investor')->plainTextToken,
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }

    public function login(string $email, string $password): User
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new InvalidArgumentException('Invalid credentials.');
        }

        return $user;
    }
public function forgotPassword(string $email, string $password ): ?string
{
    $user = $this->userRepository->findByEmail($email);

    if (! $user) {
        return null;
    }

    $result =ActionOtp::to($email)->for('password-reset')->send(
    new PasswordResetAction($email, $password),
    Notification::route('mail', $email)
);

    return $result->challenge;
}
public function verifyPasswordResetOtp(string $email, string $code, string $challenge
) 
    {
       $result = ActionOtp::to($email)->for('password-reset')->withChallenge($challenge)->verify($code);

        return match ($result->status) {
            OtpStatus::Matched => response()->json(['ok' => true]),
            OtpStatus::Throttled => response()->json(['message' => $result->message], 429),
            default => response()->json(['message' => $result->message], 422),
        };
    }
}

// $result = ActionOtp::to('080natty@gmail.com')->for('password-reset')->send(new \App\OtpActions\PasswordResetAction('080natty@gmail.com','080Natty'),Notification::route('mail', '080natty@gmail.com'));

// $identifierHash = \Jtargosz\ActionOtp\Support\Keys::hash('080natty@gmail.com');
// $challengeHash = \Jtargosz\ActionOtp\Support\Keys::hash($challenge);
// $key = \Jtargosz\ActionOtp\Support\Keys::record(
//     $identifierHash,
//     $challengeHash
// );

// ActionOtp::to('080natty@gmail.com')->for('password-reset')->withChallenge('yWsiYFYLnk9XmOlQi37J9fisIAB7T1Adrzat33a4')->verify('051133');