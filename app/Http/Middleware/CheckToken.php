<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $expectedToken = (string) config('services.kelola.token', '');
        $token = (string) $request->bearerToken();

        if (blank($expectedToken)) {
            return response()->json([
                'message' => 'API token is not configured.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (blank($token) || ! hash_equals($expectedToken, $token)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
