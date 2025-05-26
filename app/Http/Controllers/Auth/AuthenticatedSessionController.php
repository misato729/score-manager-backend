<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */


     public function store(LoginRequest $request): JsonResponse
     {
         // ✅ 認証チェック（失敗時にJSONでエラー返す）
         if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
             throw ValidationException::withMessages([
                 'email' => ['認証に失敗しました。'],
             ]);
         }
     
         $request->session()->regenerate();
     
         return response()->json([
             'message' => 'ログイン成功',
             'user' => $request->user()
         ]);
     }
    

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
