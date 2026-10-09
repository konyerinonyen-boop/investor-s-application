<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Verify the user's email address.
     */
    public function __invoke(
        Request $request,$id,string $hash): JsonResponse {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'error_code' => 'USER_NOT_FOUND',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Email Hash
        |--------------------------------------------------------------------------
        */

        if (
            !hash_equals(
                sha1($user->getEmailForVerification()),
                $hash
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email verification link.',
                'error_code' => 'INVALID_VERIFICATION_LINK',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Already Verified
        |--------------------------------------------------------------------------
        */

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Your email address is already verified.',
                'data' => [
                    'email_verified' => true,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Mark Email As Verified
        |--------------------------------------------------------------------------
        */

        $user->markEmailAsVerified();

        return response()->json([
            'success' => true,
            'message' => 'Your email address has been verified successfully.',
            'data' => [
                'email_verified' => true,
            ],
        ]);
    }
}