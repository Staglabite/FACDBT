<?php

namespace App\Http\Controllers\Api\HITUAM\HITUAM02;

use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Services\HITUAM\LoginThrottleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use App\Models\HITUAM01\HITUAM_MSHUSER as User;

class HITUAMF009 extends Controller
{
    public function __construct(private LoginThrottleService $loginThrottle) {}

    public function login(Request $request)
    {
        $username = (string) $request->username;
        $ipAddress = $request->ip();

        $lockState = $this->loginThrottle->ensureIsNotLocked($ipAddress);
        if ($lockState) {
            return Response::error(
                sprintf(
                    'Account temporarily locked after %d failed attempts. Try again at %s.',
                    $lockState['max_attempts'],
                    $this->loginThrottle->formatLockedUntil($lockState['locked_until'])
                ),
                423
            );
        }

        $user = User::query()
            ->where('VUSERNAME', $username)
            ->first();

        if (!$user) {
            return $this->failedLoginResponse($ipAddress);
        }

        if (! Hash::check($request->password, $user->VPASSWORD)) {
            return $this->failedLoginResponse($ipAddress);
        }

        $this->loginThrottle->clear($ipAddress);

        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return Response::success(['token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return Response::success(message: 'Logout successful');
    }

    private function failedLoginResponse(?string $ipAddress)
    {
        $throttleState = $this->loginThrottle->recordFailedAttempt($ipAddress);

        if ($throttleState['is_locked']) {
            return Response::error(
                sprintf(
                    'Account temporarily locked after %d failed attempts. Try again at %s.',
                    $throttleState['max_attempts'],
                    $this->loginThrottle->formatLockedUntil($throttleState['locked_until'])
                ),
                423
            );
        }

        return Response::error('You have entered an invalid username or password', 404);
    }
}
