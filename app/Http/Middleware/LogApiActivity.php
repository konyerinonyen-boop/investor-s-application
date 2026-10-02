<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();
        $route = $request->route();

        if ($user !== null) {
            $method = $request->method();
            $endpoint = $route?->uri() ?? 'unknown';

            AuditLog::create([
                'user_id' => $user->getAuthIdentifier(),
                'action' => 'api.request',
                'model' => 'http.request',
                'old_values' => null,
                'new_values' => [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status' => $response->getStatusCode(),
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                ],
                'message' => "{$method} {$endpoint} returned {$response->getStatusCode()}.",
            ]);
        }

        return $response;
    }
}
