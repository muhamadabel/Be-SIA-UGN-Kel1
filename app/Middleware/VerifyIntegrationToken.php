<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIntegrationToken
{
    /**
     * Verify the X-Integration-Token header for server-to-server (webhook) requests.
     * This middleware authenticates incoming requests from trusted backend services
     * (e.g., SIA Pendaftaran) using a shared secret token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Integration-Token');
        $expectedToken = config('services.admissions.token');

        if (empty($expectedToken) || empty($token) || !hash_equals($expectedToken, $token)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid integration token.'
            ], 401);
        }

        return $next($request);
    }
}
