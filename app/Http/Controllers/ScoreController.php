<?php

namespace App\Http\Controllers;

use App\Models\Score;
use Illuminate\Http\Request;

class ScoreController extends Controller
{
    // ✅ ゲストでも閲覧可能なスコア取得API
    public function index(Request $request)
    {
        $userId = $request->query('user');

        if (!$userId) {
            return response()->json(['error' => 'User ID is required'], 400);
        }

        return Score::with('song')
            ->where('user_id', $userId)
            ->get();
    }

    // ✅ 編集API（ログインユーザーのみ）
    public function update(Request $request, Score $score)
    {
        $validated = $request->validate([
            'rank' => ['nullable', 'string', 'max:10'],
            'fc' => ['required', 'boolean'],
        ]);

        $score->update($validated);

        return response()->json(['message' => 'Score updated']);
    }
}
