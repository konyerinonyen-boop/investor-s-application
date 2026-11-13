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
     * API: Validate account type and prepare Google authentication.
     */
    public function prepareGoogleRegistration(Request $request)
    {
        $validated = $request->validate([
            'account_type' => ['required', 'string', 'in:individual,entity'],
        ]);

        // Generate an unpredictable, one-time transaction identifier.
        $transaction = Str::random(64);

        // Store the selected type temporarily, keyed by a hash.
        Cache::put(
            'google_oauth_transaction:' . hash('sha256', $transaction),
            [
                'account_type' => $validated['account_type'],
            ],
            now()->addMinutes(5)
        );

        // The browser will visit this URL to begin OAuth.
        $authorizationUrl = route('google.redirect', [
            'transaction' => $transaction,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Google authentication is ready.',
            'data' => [
                'authorization_url' => $authorizationUrl,
                'expires_in' => 300,
            ],
        ]);
    }

    /**
     * Browser: Consume the transaction and start Socialite.
     */
    public function redirectToGoogle(string $transaction)
    {
        $cacheKey = 'google_oauth_transaction:' .
            hash('sha256', $transaction);

        // A transaction can only be used once.
        $registration = Cache::pull($cacheKey);

        if (
            ! is_array($registration) ||
            ! in_array(
                $registration['account_type'] ?? null,
                ['individual', 'entity'],
                true
            )
        ) {
            return redirect()->away(
                rtrim(config('services.frontend_url'), '/') .
                '/login?error=google_registration_expired'
            );
        }

        // Save the selection in the web session.
        // Socialite will use this same session for OAuth state validation.
        session([
            'google_registration.account_type' =>
                $registration['account_type'],
        ]);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Browser: Handle Google's callback.
     */
    public function handleGoogleCallback()
    {
        $frontendUrl = rtrim(config('services.frontend_url'), '/');

        try {
            // Keep Socialite's normal state validation enabled.
            $googleUser = Socialite::driver('google')->user();

            if (! $googleUser instanceof GoogleUser) {
                return redirect()->away(
                    $frontendUrl . '/login?error=google_auth_failed'
                );
            }

            $googleData = $googleUser->user;
            $email = $googleUser->getEmail();
            $googleId = $googleUser->getId();

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
                // Existing Google-linked account.
                $user = User::where('google_id', $googleId)->first();

                if ($user) {
                    return $user;
                }

                // Existing account with the same email.
                $user = User::where('email', $email)->first();

                if ($user) {
                    if (
                        $user->google_id &&
                        $user->google_id !== $googleId
                    ) {
                        throw new \RuntimeException(
                            'This account is already linked to another Google account.'
                        );
                    }

                    // Link Google without changing the existing account type.
                    $user->forceFill([
                        'google_id' => $googleId,
                        'avatar' => $googleUser->getAvatar(),
                        'email_verified_at' =>
                            $user->email_verified_at ?? now(),
                    ])->save();

                    return $user;
                }

                // A new user must have selected an account type.
                $accountType = session()->pull(
                    'google_registration.account_type'
                );

                if (! in_array(
                    $accountType,
                    ['individual', 'entity'],
                    true
                )) {
                    throw new \RuntimeException(
                        'Registration information expired. Please try again.'
                    );
                }

                $user = new User();

                $user->forceFill([
                    'first_name' => $googleData['given_name']
                        ?? $googleUser->getName()
                        ?? 'User',
                    'last_name' => $googleData['family_name'] ?? '',
                    'email' => $email,
                    'phone' => null,
                    'password' => Hash::make(Str::random(64)),
                    'google_id' => $googleId,
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                    'status' => 'active',
                    'kyc_status' => 'not_started',
                    'country' => null,
                    'account_type' => $accountType,
                ]);

                $user->save();

                return $user;
            });

            if ($user->status !== 'active') {
                return redirect()->away(
                    $frontendUrl . '/login?error=account_inactive'
                );
            }

            // Create a short-lived, one-time code for the frontend.
            $code = Str::random(64);

            Cache::put(
                'google_oauth_exchange:' . hash('sha256', $code),
                $user->id,
                now()->addMinutes(2)
            );

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
     * API: Exchange the one-time code for a Sanctum token.
     */
    public function exchange(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:64'],
        ]);

        $cacheKey = 'google_oauth_exchange:' .
            hash('sha256', $validated['code']);

        // Pull means the code cannot be reused.
        $userId = Cache::pull($cacheKey);

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Google authentication code.',
            ], 401);
        }

        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User account not found.',
            ], 404);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active.',
            ], 403);
        }

        $token = $user->createToken('google-auth')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Google authentication successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user->load('roles'),
            ],
        ]);
    }
}
