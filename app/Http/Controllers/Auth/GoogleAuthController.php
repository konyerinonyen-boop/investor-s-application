<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's authentication page.
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google's callback.
     */
    public function handleGoogleCallback()
    {
        $frontendUrl = rtrim(config('services.frontend_url'), '/');

        try {
            $googleUser = Socialite::driver('google')->user();

            // Ensure we received Google's expected user object.
            if (! $googleUser instanceof GoogleUser) {
                return redirect()->away(
                    $frontendUrl . '/login?error=google_auth_failed'
                );
            }

            $googleData = $googleUser->user;

            $email = $googleUser->getEmail();
            $googleId = $googleUser->getId();

            // Verify that Google has verified this email address.
            $emailVerified = filter_var(
                $googleData['email_verified']
                    ?? $googleData['verified_email']
                    ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

            if (! $email || ! $googleId || ! $emailVerified) {
                return redirect()->away(
                    $frontendUrl . '/login?error=google_email_not_verified'
                );
            }

            $user = DB::transaction(function () use (
                $googleUser,
                $googleId,
                $email
            ) {
                // First, look for an account already linked to this Google ID.
                $user = User::where('google_id', $googleId)->first();

                if (! $user) {
                    // Check whether an account already exists with this email.
                    $user = User::where('email', $email)->first();

                    if ($user) {
                        // Do not connect an account already linked to
                        // a different Google account.
                        if (
                            $user->google_id &&
                            $user->google_id !== $googleId
                        ) {
                            throw new \RuntimeException(
                                'This account is already linked to another Google account.'
                            );
                        }

                        // Link the existing account to Google.
                        $user->forceFill([
                            'google_id' => $googleId,
                            'avatar' => $googleUser->getAvatar(),
                            'email_verified_at' => $user->email_verified_at
                                ?? now(),
                        ])->save();
                    } else {
                        // Create a new normal user account.
                        // No investor role or other role is assigned.
                        $user = new User();

                        $user->forceFill([
                            'first_name' => $googleUser->user['given_name']
                                ?? $googleUser->getName()
                                ?? 'User',

                            'last_name' => $googleUser->user['family_name']
                                ?? '',

                            'email' => $email,
                            'phone' => null,
                            'password' => Hash::make(Str::random(64)),
                            'google_id' => $googleId,
                            'avatar' => $googleUser->getAvatar(),
                            'email_verified_at' => now(),
                            'status' => 'active',
                            'kyc_status' => 'not_started',
                            'country' => null,
                            'account_type' => 'individual',
                        ])->save();
                    }
                }

                return $user;
            });

            // Only active accounts may authenticate.
            if ($user->status !== 'active') {
                return redirect()->away(
                    $frontendUrl . '/login?error=account_inactive'
                );
            }

            // Generate a temporary one-time exchange code.
            $code = Str::random(64);

            Cache::put(
                'google_oauth_exchange:' . hash('sha256', $code),
                $user->id,
                now()->addMinutes(2)
            );

            // Redirect to the frontend with the temporary code.
            return redirect()->away(
                $frontendUrl . '/auth/google/callback?' .
                http_build_query(['code' => $code])
            );

        } catch (Throwable $exception) {
            report($exception);

            return redirect()->away(
                $frontendUrl . '/login?error=google_auth_failed'
            );
        }
    }

    /**
     * Exchange the temporary Google authorization code
     * for a Sanctum access token.
     */
    public function exchange(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:64'],
        ]);

        $cacheKey = 'google_oauth_exchange:' .
            hash('sha256', $validated['code']);

        // Cache::pull retrieves and deletes the code.
        // Therefore, the code can only be used once.
        $userId = Cache::pull($cacheKey);

        if (! $userId) {
            return response()->json([
                'message' => 'Invalid or expired Google authentication code.',
            ], 401);
        }

        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'message' => 'User account not found.',
            ], 404);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active.',
            ], 403);
        }

        // Issue the Sanctum token.
        $token = $user->createToken('google-auth')->plainTextToken;

        return response()->json([
            'message' => 'Google authentication successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ],
        ]);
    }
}
