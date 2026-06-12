<?php

namespace App\Http\Controllers\pages;

use App\Http\Controllers\HITUAM\SsoController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomePage extends Controller
{
    public function index(Request $request, SsoController $ssoController)
    {
        if ($request->filled('signature')) {
            return $ssoController->receive($request);
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        // return view('content.pages.pages-home');
        if (Auth::user()->roles->isEmpty()) {
            return redirect()->route('no-role');
        }

        return redirect()->route('hituam.news.index');
    }

    public function noRole()
    {
        return view('content.pages.no-role', ['layout' => 'blank']);
    }
}
