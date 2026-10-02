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
            'message' => 'Investor account created successfully.',
            'data' => [
                'user' => $payload['user']->load('roles'),
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
}
