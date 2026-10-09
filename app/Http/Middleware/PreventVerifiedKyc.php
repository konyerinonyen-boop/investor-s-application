<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventVerifiedKyc
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
     public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->kyc_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Your KYC has already been verified.',
                'error_code' => 'KYC_ALREADY_VERIFIED',
                'data' => null,
                'errors' => [],
            ], 409);
        }

        return $next($request);
    }
}
