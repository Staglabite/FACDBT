<?php

namespace App\Services\HITUAM;

use App\Models\HITUAM01\HITUAM_MSHUSER as User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SsoService
{
    public function createPayload(User $user, ?string $redirect = null): array
    {
        $timestamp = now()->timestamp;

        $payload = array_filter([
            'username' => $user->VUSERNAME,
            'email' => $user->VEMAIL,
            'name' => $user->VNAME,
            'app' => config('app.code'),
            'redirect' => $this->normalizeRedirect($redirect),
            'timestamp' => $timestamp,
            'nonce' => (string) Str::uuid(),
        ], fn($value) => ! is_null($value) && $value !== '');

        $payload['signature'] = $this->sign($payload);

        return $payload;
    }

    public function buildRedirectUrl(array $payload, ?string $targetUrl = null): string
    {
        $targetUrl = $this->resolveTargetUrl($targetUrl);

        if (blank($targetUrl)) {
            throw new HttpException(500, 'SSO target URL is not configured.');
        }

        $query = http_build_query($payload);
        $separator = str_contains($targetUrl, '?') ? '&' : '?';

        return $targetUrl.$separator.$query;
    }

    public function buildDirectRedirectUrl(?string $targetUrl = null, ?string $redirect = null): string
    {
        $targetUrl = $this->resolveTargetUrl($targetUrl);

        if (blank($targetUrl)) {
            throw new HttpException(500, 'SSO target URL is not configured.');
        }

        $parts = parse_url($targetUrl);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return $origin.$this->resolveRedirect($redirect);
    }

    public function sessionKeyForTarget(string $targetUrl): string
    {
        return 'sso.authenticated_targets.'.sha1($targetUrl);
    }

    public function handleIncomingPayload(array $input): User
    {
        $payload = Arr::only($input, [
            'username',
            'email',
            'name',
            'app',
            'redirect',
            'timestamp',
            'nonce',
        ]);

        $signature = (string) ($input['signature'] ?? '');

        if (blank($signature)) {
            throw new HttpException(422, 'SSO signature is required.');
        }

        if (! $this->hasRequiredPayload($payload)) {
            throw new HttpException(422, 'SSO payload is incomplete.');
        }

        if (! hash_equals($this->sign($payload), $signature)) {
            Log::warning('SSO signature mismatch', [
                'username' => $payload['username'] ?? null,
                'source_app' => $payload['app'] ?? null,
            ]);

            throw new HttpException(401, 'SSO signature is invalid.');
        }

        $ttl = max(1, (int) config('services.sso.ttl', 60));
        $issuedAt = Carbon::createFromTimestamp((int) $payload['timestamp']);

        if ($issuedAt->lt(now()->subSeconds($ttl)) || $issuedAt->gt(now()->addMinute())) {
            throw new HttpException(401, 'SSO payload has expired.');
        }

        $user = User::query()
            ->with('roles')
            ->where('VUSERNAME', $payload['username'])
            ->first();

        if (! $user) {
            throw new HttpException(404, 'User SSO tidak ditemukan.');
        }

        Auth::login($user);

        return $user;
    }

    public function resolveRedirect(?string $redirect = null): string
    {
        return $this->normalizeRedirect($redirect) ?? '/dashboard';
    }

    public function resolveTargetUrl(?string $targetUrl = null): ?string
    {
        // Priority:
        // 1. target_url from request (typically mapped from menu.VENVAPP)
        // 2. SSO_TARGET_URL from .env as global fallback
        $resolvedTargetUrl = blank($targetUrl) ? config('services.sso.target_url') : $targetUrl;

        if (blank($resolvedTargetUrl)) {
            return null;
        }

        if (! filter_var($resolvedTargetUrl, FILTER_VALIDATE_URL)) {
            throw new HttpException(422, 'SSO target URL is invalid.');
        }

        if (! in_array(parse_url($resolvedTargetUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new HttpException(422, 'SSO target URL must use http or https.');
        }

        return $this->normalizeTargetUrl($resolvedTargetUrl);
    }

    protected function sign(array $payload): string
    {
        $secret = (string) config('services.sso.secret', '');

        if (blank($secret)) {
            throw new HttpException(500, 'SSO secret is not configured.');
        }

        ksort($payload);

        return hash_hmac('sha256', http_build_query($payload), $secret);
    }

    protected function hasRequiredPayload(array $payload): bool
    {
        foreach (['username', 'app', 'timestamp', 'nonce'] as $key) {
            if (blank($payload[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    protected function normalizeRedirect(?string $redirect): ?string
    {
        if (blank($redirect)) {
            return null;
        }

        return Str::startsWith($redirect, '/') ? $redirect : '/'.ltrim($redirect, '/');
    }

    protected function normalizeTargetUrl(string $targetUrl): string
    {
        $parts = parse_url($targetUrl);
        $path = $parts['path'] ?? '';

        if (! in_array($path, ['', '/', '/login'], true)) {
            return $targetUrl;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return $origin.'/auth/sso/receive';
    }
}
