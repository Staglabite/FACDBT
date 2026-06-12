<?php

namespace App\Services\HITUAM;

use App\Exceptions\ResponseException;
use App\Models\HITUAM01\HITUAM_MSHUSER as User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthService
{
    /**
     * Get data users from LDAP server and return that user with local user
     *
     * @param  string  $username  - login filed
     * @param  string  $password  - user's password
     * @return App\Models\HITUAM01\HITUAM_MSHUSER | null
     */
    public function getUserLdap(string $username, string $password): ?User
    {
        $ldapUrl = config('services.ldap.url');
        $ldapToken = config('services.ldap.token');

        if (! $ldapUrl) {
            return null;
        }

        if (! $ldapToken) {
            throw new ResponseException('API Token has not been set in the Configuration menu');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$ldapToken}",
            ])->post($ldapUrl, ['Username' => $username, 'Password' => $password]);
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), ['tag' => 'AUTH-LDAP']);
            throw new ResponseException('Failed to connect to LDAP Server');
        }

        if (! $response->successful()) {
            Log::error('Error occured in LDAP Server.', [
                'tag' => 'AUTH-LDAP',
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
            // throw new ResponseException('Error occured in LDAP Server');
        }

        if (! $response->json()) {
            throw new ResponseException('LDAP returned null');
        }

        $jsonData = $response->json();

        // If LDAP authentication fails
        if (isset($jsonData['success']) && $jsonData['success'] === false) {
            return null;
        }

        $user = $this->newUserQuery()
            ->where('VUSERNAME', $username)
            ->where('VEMPNO', $jsonData['npk'])
            ->first();

        if (! $user) {
            // Create new user based on LDAP data
            return $this->createUserFromLdap([
                'VEMPNO' => $jsonData['npk'],
                'VNAME' => $jsonData['username'],
                'VUSERNAME' => $jsonData['username'],
                'VEMAIL' => $jsonData['email'],
                'VPASSWORD' => Hash::make($password),
                'VCREA' => 'LDAP',
            ]);
        } else {
            $user->update([
                'VEMPNO' => $jsonData['npk'],
                'VNAME' => $jsonData['username'],
                'VUSERNAME' => $jsonData['username'],
                'VEMAIL' => $jsonData['email'],
                'VPASSWORD' => Hash::make($password),
                'VMODI' => 'LDAP',
            ]);
        }

        return $user;
    }

    protected function newUserQuery()
    {
        return User::query();
    }

    protected function createUserFromLdap(array $attributes): User
    {
        return User::create($attributes);
    }

    /**
     * Check is user logged in other device.
     *
     * @param  App\Models\HITUAM01\HITUAM_MSHUSER  $user
     */
    public function userLoggedInOtherDevice(User $user): bool
    {
        $currentApplication = config('app.code');

        try {
            $session = DB::connection('hituam')
                ->table('sessions')
                ->where('user_id', $user->IID)
                ->where('application', $currentApplication)
                ->orderByDesc('last_activity')
                ->first();

            if (! $session) {
                return false;
            }

            // Konversi last_activity ke Carbon object
            $lastActivity = Carbon::createFromTimestamp($session->last_activity);

            // Hitung durasi session timeout (misalnya 2 jam)
            $sessionLifetime = config('session.lifetime', 120); // dalam menit
            $sessionExpiry = $lastActivity->addMinutes($sessionLifetime);

            // Jika session sudah expired, hapus dan return false
            if (now()->greaterThan($sessionExpiry)) {
                DB::connection('hituam')
                    ->table('sessions')
                    ->where('id', $session->id)
                    ->delete();

                Log::info('Expired session deleted', [
                    'user_id' => $user->IID,
                    'session_id' => $session->id,
                    'tag' => 'AUTH-SESSION'
                ]);

                return false;
            }

            // Session masih aktif, berarti user login di device lain
            Log::warning('User attempting to login from another device', [
                'user_id' => $user->IID,
                'username' => $user->VUSERNAME,
                'existing_session_id' => $session->id,
                'last_activity' => $lastActivity->toDateTimeString(),
                'tag' => 'AUTH-SESSION'
            ]);

            return true;
        } catch (\Throwable $th) {
            Log::error('Error checking user session', [
                'user_id' => $user->IID ?? null,
                'error' => $th->getMessage(),
                'tag' => 'AUTH-SESSION'
            ]);

            throw new ResponseException($th->getMessage());
        }
    }

    /**
     * Force logout user from other devices
     *
     * @param  App\Models\HITUAM01\HITUAM_MSHUSER  $user
     * @return bool
     */
    public function forceLogoutOtherDevices(User $user): bool
    {
        $currentApplication = config('app.code');

        try {
            $deleted = DB::connection('hituam')
                ->table('sessions')
                ->where('user_id', $user->IID)
                ->where('application', $currentApplication)
                ->delete();

            if ($deleted > 0) {
                Log::info('Force logout from other devices', [
                    'user_id' => $user->IID,
                    'sessions_deleted' => $deleted,
                    'tag' => 'AUTH-SESSION'
                ]);
            }

            return true;
        } catch (\Throwable $th) {
            Log::error('Error forcing logout', [
                'user_id' => $user->IID,
                'error' => $th->getMessage(),
                'tag' => 'AUTH-SESSION'
            ]);

            return false;
        }
    }
}
