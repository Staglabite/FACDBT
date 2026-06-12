<?php

namespace App\Http\Controllers\HITUAM;

use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Services\HITUAM\SsoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SsoController extends Controller
{
    public function __construct(private SsoService $ssoService) {}

    public function send(Request $request)
    {
        if (! Auth::check()) {
            return Response::error('User tidak terautentikasi.', 401);
        }

        try {
            $redirect = $request->string('redirect')->toString();
            // target_url can be sent per menu click; if omitted, service falls back to SSO_TARGET_URL.
            $targetUrl = $this->ssoService->resolveTargetUrl($request->string('target_url')->toString());

            if (blank($targetUrl)) {
                throw new HttpException(500, 'SSO target URL is not configured.');
            }

            $payload = $this->ssoService->createPayload(Auth::user(), $redirect);
            $targetUrl = $this->ssoService->buildRedirectUrl($payload, $targetUrl);
        } catch (HttpException $exception) {
            if ($request->expectsJson()) {
                return Response::error($exception->getMessage(), $exception->getStatusCode());
            }

            return back()->with('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        if ($request->boolean('json') || $request->expectsJson()) {
            return Response::success([
                'target_url' => $targetUrl,
                'payload' => $payload,
            ], 'SSO payload generated.');
        }

        return redirect()->away($targetUrl);
    }

    public function receive(Request $request)
    {
        try {
            $user = $this->ssoService->handleIncomingPayload(
                $request->only([
                    'username',
                    'email',
                    'name',
                    'app',
                    'redirect',
                    'timestamp',
                    'nonce',
                    'signature',
                ])
            );

            $request->session()->regenerate();

            $redirect = $this->ssoService->resolveRedirect($request->string('redirect')->toString());

            if ($request->expectsJson()) {
                return Response::success([
                    'user' => [
                        'id' => $user->IID,
                        'username' => $user->VUSERNAME,
                        'email' => $user->VEMAIL,
                    ],
                    'redirect' => $redirect,
                ], 'SSO login successful.');
            }

            return redirect()->to($redirect);
        } catch (HttpException $exception) {
            if ($request->expectsJson()) {
                return Response::error($exception->getMessage(), $exception->getStatusCode());
            }


            $redirect = Auth::check()
                ? redirect()->route(Auth::user()->roles->isEmpty() ? 'no-role' : 'hituam.news.index')
                : redirect()->route('login');

            return $redirect->with('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
