<?php

namespace App\Services\HITUAM;

// use App\Models\FACTWM01\FACTWM_MSHCONFIGURATION as Config;
use App\Models\HITUAM01\HITUAM_MSHCONFIGURATION as Config;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class LoginThrottleService
{
    private const DEFAULT_MAX_FAILED_ATTEMPTS = 5;

    private const DEFAULT_LOCKOUT_MINUTES = 15;

    private function store(): CacheRepository
    {
        return Cache::build([
            'driver' => 'database',
            'table' => 'cache',
            'connection' => 'hituam',
        ]);
    }

    public function ensureIsNotLocked(?string $ipAddress): ?array
    {
        return $this->lockState($this->ipIdentifier($ipAddress));
    }

    public function recordFailedAttempt(?string $ipAddress): array
    {
        return $this->recordFailedAttemptFor($this->ipIdentifier($ipAddress));
    }

    public function clear(?string $ipAddress): void
    {
        $this->clearIdentifier($this->ipIdentifier($ipAddress));
    }

    public function maxFailedAttempts(): int
    {
        return max(
            (int) (Config::where('VVARIABLE', 'LOGIN_MAX_FAILED_ATTEMPTS')->value('VVALUE') ?? self::DEFAULT_MAX_FAILED_ATTEMPTS),
            1
        );
    }

    public function lockoutMinutes(): int
    {
        return max(
            (int) (Config::where('VVARIABLE', 'LOGIN_LOCKOUT_MINUTES')->value('VVALUE') ?? self::DEFAULT_LOCKOUT_MINUTES),
            1
        );
    }

    public function formatLockedUntil(Carbon $lockedUntil): string
    {
        return $lockedUntil
            ->copy()
            ->setTimezone('Asia/Jakarta')
            ->format('d M Y H:i:s');
    }

    private function lockState(array $identifier): ?array
    {
        $lockUntil = $this->store()->get($this->lockKey($identifier));

        if (empty($lockUntil)) {
            $attempts = (int) $this->store()->get($this->attemptKey($identifier), 0);

            if ($attempts < $this->maxFailedAttempts()) {
                return null;
            }

            $lockUntil = $this->lockIdentifier($identifier)->timestamp;
        }

        $lockedUntil = Carbon::createFromTimestamp((int) $lockUntil);

        if (now()->greaterThanOrEqualTo($lockedUntil)) {
            $this->clearIdentifier($identifier);

            return null;
        }

        return [
            'locked_until' => $lockedUntil,
            'max_attempts' => $this->maxFailedAttempts(),
            'lockout_minutes' => $this->lockoutMinutes(),
            'scope' => $identifier['scope'],
        ];
    }

    private function recordFailedAttemptFor(array $identifier): array
    {
        $attempts = ((int) $this->store()->get($this->attemptKey($identifier), 0)) + 1;
        $this->store()->put($this->attemptKey($identifier), $attempts, now()->addMinutes($this->lockoutMinutes()));

        $isLocked = $attempts >= $this->maxFailedAttempts();
        $lockedUntil = null;

        if ($isLocked) {
            $lockedUntil = $this->lockIdentifier($identifier);
        }

        return [
            'attempts' => $attempts,
            'remaining_attempts' => max($this->maxFailedAttempts() - $attempts, 0),
            'is_locked' => $isLocked,
            'locked_until' => $lockedUntil,
            'max_attempts' => $this->maxFailedAttempts(),
            'lockout_minutes' => $this->lockoutMinutes(),
            'scope' => $identifier['scope'],
        ];
    }

    private function attemptKey(array $identifier): string
    {
        return 'login:attempts:' . $identifier['scope'] . ':' . sha1($identifier['value']);
    }

    private function lockKey(array $identifier): string
    {
        return 'login:lock:' . $identifier['scope'] . ':' . sha1($identifier['value']);
    }

    private function lockIdentifier(array $identifier): Carbon
    {
        $lockedUntil = now()->addMinutes($this->lockoutMinutes());

        $this->store()->put(
            $this->lockKey($identifier),
            $lockedUntil->timestamp,
            $lockedUntil
        );

        $this->store()->forget($this->attemptKey($identifier));

        return $lockedUntil;
    }

    private function clearIdentifier(array $identifier): void
    {
        $this->store()->forget($this->attemptKey($identifier));
        $this->store()->forget($this->lockKey($identifier));
    }

    private function ipIdentifier(?string $ipAddress): array
    {
        return [
            'scope' => 'ip',
            'value' => $this->normalizeIpAddress($ipAddress ?: 'unknown'),
        ];
    }

    private function normalizeIpAddress(string $ipAddress): string
    {
        return mb_strtolower(trim($ipAddress));
    }
}
