<?php

namespace App\Http\Controllers\Auth;


use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;


class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */

public function store(Request $request)
{
    // 基本のバリデーション
    $credentials = $request->validate([
        'email'    => ['required','string','email'],
        'password' => ['required','string'],
    ]);

    // 認証トライ
    if (Auth::attempt($credentials, $request->boolean('remember'))) {
        $request->session()->regenerate();

        // ★ ここで制限：IDが10以外は弾く
        if ((int) Auth::id() !== (int) env('ALLOWED_USER_ID'))  {
            Auth::logout();
            // セッション再生成してCSRF/Sessionをクリーンに
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'このアカウントではログインできません。'])
                ->onlyInput('email');
        }

        // OKならダッシュボードへ（適宜変更）
        return redirect()->intended(route('dashboard'));
    }

    // 失敗時
    throw ValidationException::withMessages([
        'email' => ['認証に失敗しました。'],
    ])->redirectTo(url()->previous());
}


    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
