<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class AuthService
{
    public function __construct(protected UserRepository $userRepository)
    {
    }

    public function register(array $data): array
    {
        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'kyc_status' => 'not_started',
            'status' => 'active',
        ]);

        $this->userRepository->assignInvestorRole($user);

        return [
            'user' => $user->fresh()->load('roles'),
            'token' => $user->createToken('investor')->plainTextToken,
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
}
