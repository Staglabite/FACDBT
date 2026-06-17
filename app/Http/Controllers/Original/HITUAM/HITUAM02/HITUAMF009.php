<?php

namespace App\Http\Controllers\Original\HITUAM\HITUAM02;

use App\Http\Controllers\Controller;
use App\Http\Requests\HITUAM\Auth\LoginRequest;
use App\Mail\ForgotPasswordMail;
use App\Models\HITUAM01\HITUAM_MSHCONFIGURATION as Config;
use App\Models\HITUAM01\HITUAM_LOGLOGINHISTORY as LoginHistory;
use App\Models\HITUAM01\HITUAM_MSDUSER_REMEMBER_TOKEN as UserRememberToken;
use App\Models\HITUAM01\HITUAM_MSHUSER as User;
use App\Services\HITUAM\LoginHistoryService;
use App\Services\HITUAM\AuthService;
use App\Services\HITUAM\LoginThrottleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Auth Controller
class HITUAMF009 extends Controller
{
    public function __construct(
        private AuthService $authService,
        private LoginHistoryService $loginHistory,
        private LoginThrottleService $loginThrottle
    ) {}

    private function getTokenExpiryHours(): int
    {
        $tokenExpiryConfig = Config::where('VVARIABLE', 'TOKEN_EXPIRY_HOURS')->first();

        return $tokenExpiryConfig ? (int) $tokenExpiryConfig->VVALUE : 5;
    }

    private function getCacheExpiryHours(): int
    {
        $cacheExpiryConfig = Config::where('VVARIABLE', 'CACHE_EXPIRY_HOURS')->first();

        return $cacheExpiryConfig ? (int) $cacheExpiryConfig->VVALUE : 1;
    }

    public function index()
    {
        if (Auth::check()) {
            return redirect()->intended();
        }

        $pageConfigs = ['myLayout' => 'blank'];

        return view('modules.HITUAM.HITUAM02.HITUAMF009.HITUAMF009', ['pageConfigs' => $pageConfigs]);
    }

    public function login(LoginRequest $request)
    {
        $username = (string) $request->username;
        $ipAddress = $request->ip();

        $lockState = $this->loginThrottle->ensureIsNotLocked($ipAddress);
        if ($lockState) {
            throw ValidationException::withMessages([
                'username' => sprintf(
                    'Account temporarily locked after %d failed attempts. Try again at %s.',
                    $lockState['max_attempts'],
                    $this->loginThrottle->formatLockedUntil($lockState['locked_until'])
                ),
            ]);
        }

        // Check in LDAP first
        $user = $this->authService->getUserLdap($username, $request->password);

        if (! $user) {
            // If LDAP fails, check local database
            $user = User::query()
                ->with(['roles'])
                ->where('VUSERNAME', $username)
                ->first();
        }

        if (! $user) {
            $this->throwFailedLoginValidation($ipAddress);

            throw ValidationException::withMessages([
                'username' => 'You have entered an invalid username or password',
            ]);
        }

        if (! Hash::check($request->password, $user->VPASSWORD)) {
            $this->throwFailedLoginValidation($ipAddress);

            throw ValidationException::withMessages([
                'username' => 'You have entered an invalid username or password',
            ]);
        }

        $hasActiveRole = $user->roles()
            ->where('HITUAM_MSHROLES.BSTATUS', true)
            ->exists();

        if ($user->roles->isNotEmpty() && ! $hasActiveRole) {
            throw ValidationException::withMessages([
                'username' => 'Your role is inactive. Please contact the administrator.',
            ]);
        }

        $this->loginThrottle->clear($ipAddress);

        // Is this account login in other device?
        if ($this->authService->userLoggedInOtherDevice($user)) {
            throw ValidationException::withMessages([
                'username' => 'Your account is currently active on another device. Please logout from the other device first or contact administrator.',
            ]);
        }

        Auth::login($user, $request->has('remember'));

        $isUserSupplier = null;
        $this->loginHistory->writeHis($user, $isUserSupplier, $request);

        $privasiText = Config::where('VVARIABLE', 'pengaturan_privasi_anda')->first();
        if ($request->ajax()) {
            $redirectUrl = redirect()->route('hituam.news.index')->getTargetUrl();
            if ($user->roles->isEmpty()) {
                $redirectUrl = redirect()->route('no-role')->getTargetUrl();
            }

            return response()->json([
                'success' => true,
                'name' => Auth::user()->VUSERNAME,
                'privacy' => $privasiText?->VVALUE,
                'redirect' => $redirectUrl,
            ]);
        }

        return redirect()->intended('/');
    }

    private function throwFailedLoginValidation(?string $ipAddress): void
    {
        $throttleState = $this->loginThrottle->recordFailedAttempt($ipAddress);

        if ($throttleState['is_locked']) {
            throw ValidationException::withMessages([
                'username' => sprintf(
                    'Account temporarily locked after %d failed attempts. Try again at %s.',
                    $throttleState['max_attempts'],
                    $this->loginThrottle->formatLockedUntil($throttleState['locked_until'])
                ),
            ]);
        }
    }

    public function logout(Request $request)
    {
        $request->session()->forget('information_shown');
        $userId = Auth::id();
        $currentApplication = config('app.code');

        // Delete remember token for this application
        if ($userId) {
            UserRememberToken::where('user_id', $userId)
                ->where('application', $currentApplication)
                ->delete();

            DB::connection('hituam')
                ->table('sessions')
                ->where('user_id', $userId)
                ->delete();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Cek apakah request dari AJAX
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil',
            ]);
        }

        return redirect()->route('login');
    }

    public function forgot()
    {
        if (Auth::check()) {
            return redirect()->intended();
        }

        $pageConfigs = ['myLayout' => 'blank'];

        return view('modules.HITUAM.HITUAM02.HITUAMF009.HITUAMF009', ['pageConfigs' => $pageConfigs]);
    }

    public function aforgot(Request $request)
    {
        $token = Str::random(64);
        $data = $request->validate([
            'email' => 'required|email|exists:hituam.HITUAM_MSHUSER,VEMAIL',
        ], [
            'email.required' => 'Email wajib diisi',
            'email.email' => 'Format email tidak valid',
            'email.exists' => 'Email tidak terdaftar dalam sistem',
        ]);

        $existingToken = DB::connection('hituam')
            ->table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        $tokenExpiryHours = (int) $this->getTokenExpiryHours();

        if ($existingToken) {

            $tokenAge = Carbon::parse($existingToken->created_at);
            $expiryTime = $tokenAge->addHours($tokenExpiryHours);

            if (Carbon::now()->lessThan($expiryTime)) {
                return redirect()->route('forgot.password')->with('swal', [
                    'type' => 'warning',
                    'title' => 'Perhatian!',
                    'text' => 'Link reset password sudah dikirim sebelumnya, Silakan cek email Anda.',
                    'timer' => 5000,
                    'position' => 'top-end',
                ]);
            } else {
                DB::connection('hituam')
                    ->table('password_reset_tokens')
                    ->where('email', $data['email'])
                    ->delete();
            }
        }

        DB::connection('hituam')->table('password_reset_tokens')->insert([
            'email' => $data['email'],
            'token' => $token,
            'created_at' => Carbon::now(),
        ]);

        Mail::to($request->email)->queue(new ForgotPasswordMail($token));

        return redirect()->route('forgot.password')->with('swal', [
            'type' => 'success',
            'title' => 'Success!',
            'text' => 'Jika email terdaftar, kami telah mengirimkan link reset password ke email tersebut.',
            'timer' => 4000,
            'position' => 'top-end',
        ]);
    }

    public function reset($token)
    {
        $pageConfigs = ['myLayout' => 'blank'];
        $updatePassword = DB::connection('hituam')->table('password_reset_tokens')->where([
            'token' => $token,
        ])->first();

        $tokenExpiryHours = (int) $this->getTokenExpiryHours();

        if (! $updatePassword) {
            return redirect()->route('forgot.password')->with('swal', [
                'type' => 'error',
                'title' => 'Invalid Token',
                'text' => 'The password reset token is invalid or has expired.',
                'timer' => 4000,
                'position' => 'top-end',
            ]);
        }

        $tokenAge = Carbon::parse($updatePassword->created_at);
        $expiryTime = $tokenAge->addHours($tokenExpiryHours);

        if (Carbon::now()->greaterThan($expiryTime)) {
            DB::connection('hituam')->table('password_reset_tokens')->where([
                'token' => $token,
            ])->delete();

            return redirect()->route('forgot.password')->with('swal', [
                'type' => 'error',
                'title' => 'Token Expired',
                'text' => 'Link reset password telah kadaluarsa. Silakan kirim ulang permintaan reset password.',
                'timer' => 5000,
                'position' => 'top-end',
            ]);
        }

        return view('modules.HITUAM.HITUAM02.HITUAMF009.HITUAMF009', compact('token', 'pageConfigs'));
    }

    public function postReset(Request $request)
    {
        try {
            $data = $request->validate([
                'token' => 'nullable',
                'password' => 'required|string|min:8|confirmed',
                'password_confirmation' => 'required',
            ]);

            $updatePassword = DB::connection('hituam')->table('password_reset_tokens')->where([
                'token' => $request->token,
            ])->first();

            $tokenExpiryHours = (int) $this->getTokenExpiryHours();

            if (! $updatePassword) {
                return redirect()->route('reset.password', $request->token)->with('swal', [
                    'type' => 'error',
                    'title' => 'Invalid Token',
                    'text' => 'The password reset token is invalid or has expired.',
                    'timer' => 4000,
                    'position' => 'top-end',
                ]);
            }

            $tokenAge = Carbon::parse($updatePassword->created_at);
            $expiryTime = $tokenAge->addHours($tokenExpiryHours);

            if (Carbon::now()->greaterThan($expiryTime)) {
                DB::connection('hituam')->table('password_reset_tokens')->where([
                    'token' => $request->token,
                ])->delete();

                return redirect()->route('forgot.password')->with('swal', [
                    'type' => 'error',
                    'title' => 'Token Expired',
                    'text' => 'Link reset password telah kadaluarsa. Silakan kirim ulang permintaan reset password.',
                    'timer' => 5000,
                    'position' => 'top-end',
                ]);
            }

            User::where('VEMAIL', $updatePassword->email)->update([
                'VPASSWORD' => Hash::make($data['password']),
            ]);

            DB::connection('hituam')->table('password_reset_tokens')->where('email', $updatePassword->email)->delete();

            return redirect()->route('login')->with('swal', [
                'type' => 'success',
                'title' => 'Success',
                'text' => 'Your password has been changed successfully..',
                'timer' => 4000,
                'position' => 'top-end',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    }

    public function savePrivacyAgreement(Request $request)
    {
        $userId = Auth::id();

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        $agreed = $request->input('agreed', false);

        LoginHistory::where('VUSERNAME', Auth::user()->VUSERNAME)
            ->orderByDesc('DLASTLOGIN')
            ->limit(1)
            ->update([
                'BISACCEPTPRIVACY' => $agreed,
                'DMODI' => now(),
            ]);

        $cacheKey = "privacy_agreement_{$userId}";
        $cacheData = [
            'user_id' => $userId,
            'agreed' => $agreed,
            'timestamp' => now()->toDateTimeString(),
        ];

        $cacheHours = $this->getCacheExpiryHours();

        // Simpan ke cache sesuai durasi (jam) yang di konfigurasi
        Cache::put($cacheKey, $cacheData, now()->addHours($cacheHours));

        return response()->json([
            'success' => true,
            'message' => 'Persetujuan privacy berhasil disimpan',
        ]);
    }

    public function checkPrivacyAgreement()
    {
        $userId = Auth::id();

        if (! $userId) {
            return response()->json([
                'agreed' => false,
            ]);
        }

        $cacheKey = "privacy_agreement_{$userId}";

        // Cek apakah cache masih ada
        $agreement = Cache::get($cacheKey);

        return response()->json([
            'agreed' => ! empty($agreement),
        ]);
    }
}
