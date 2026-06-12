<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckPrivacyAgreement
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = Auth::id();

        if ($userId) {
            $cacheKey = "privacy_agreement_{$userId}";
            $agreement = Cache::get($cacheKey);

            // Jika belum ada agreement atau sudah expired
            if (!$agreement) {
                return redirect()->route('privacy.prompt');
            }
        }

        return $next($request);
    }
}
