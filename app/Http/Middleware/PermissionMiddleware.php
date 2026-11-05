<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $serviceName): Response
    {
        $user = Auth::user();
        if (! $user || ! in_array($serviceName, $user->serviceNames())) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
