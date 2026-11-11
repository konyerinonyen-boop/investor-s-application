<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFrontendApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow browser CORS preflight requests.
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $configuredKey = config('api.frontend_api_key');

        $providedKey = $request->header('X-API-KEY');

        // Fail closed if the server key is not configured.
        if (
            ! is_string($configuredKey) ||
            $configuredKey === '' ||
            ! is_string($providedKey) ||
            $providedKey === '' ||
            ! hash_equals($configuredKey, $providedKey)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing API key.',
            ], 401);
        }

        return $next($request);
    }
}
