<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController
{
    public function __construct(protected AuthService $authService)
    {
    }

    public function register(RegisterRequest $request)
    {
        $payload = $this->authService->register($request->validated());

        return response()->json([
            'success' => true,
            'message' =>
                'Registration successful..You have to verify your email ',

            'data' => [
                'user' => $payload['user']->load('roles'),
                'email_verified' => $payload['email_verified'],
                'token' => $payload['token'],
            ],
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        try {
            $user = $this->authService->login($request->email, $request->password);
            $token = $user->createToken('investor')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Signed in successfully.',
                'data' => [
                    'user' => $user->load('roles'),
                    'token' => $token,
                ],
            ]);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 401);
        }
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->load('roles', 'kycProfile'),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Signed out successfully.',
        ]);
    }


public function forgotPassword(Request $request)
{
    $request->validate([
        'email' => ['required', 'email'],
        'new_password' => ['required', 'string'],
    ]);

    try {
        $challenge = $this->authService->forgotPassword(
            $request->email, $request->new_password
        );

        return response()->json([
            'success' => true,
            'message' => 'If an account exists with this email, a password reset code has been sent.',
            'data' => [
                'challenge' => $challenge,
            ],
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}
public function verifyPasswordResetOtp(Request $request)
{
    $request->validate([
        'email' => ['required', 'email'],
        'code' => ['required', 'string'],
        'challenge' => ['required', 'string'],
    ]);

    try {
        $result = $this->authService->verifyPasswordResetOtp(
            $request->email,
            $request->code,
            $request->challenge,
        );

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
            'data' => [
                'result' => $result,
            ],
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
}

}