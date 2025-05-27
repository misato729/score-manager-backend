<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Song;
use App\Models\Score;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function store(Request $request)
    {
        // ✅ バリデーション（必要に応じて調整）
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:8',
        ]);

        // ✅ ユーザー作成
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // ✅ 全曲分のスコアを初期化（rank: '', fc: false）
        $songs = Song::all();

        foreach ($songs as $song) {
            Score::create([
                'user_id' => $user->id,
                'song_id' => $song->id,
                'rank' => '',
                'fc' => false,
            ]);
        }

        return response()->json(['message' => 'User created', 'user_id' => $user->id]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // 自分自身以外は削除できないように
        if (auth()->id() !== (int)$id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Account deleted']);
    }
}
