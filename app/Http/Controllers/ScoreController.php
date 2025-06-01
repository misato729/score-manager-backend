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
    public function userScores(Request $request)
    {
        $userId = $request->query('user');

        $results = DB::table('songs')
            ->leftJoin('scores', function($join) use ($userId) {
                $join->on('songs.id', '=', 'scores.song_id')
                     ->where('scores.user_id', '=', $userId);
            })
            ->select(
                'songs.id as song_id',
                'songs.title',
                'songs.jiriki_rank',
                'scores.id as score_id',
                'scores.rank',
                'scores.fc',
                'scores.user_id'
            )
            ->get();

        return response()->json($results);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'song_id' => ['required', 'integer'],
            'rank' => ['nullable', 'string', 'max:10'],
            'fc' => ['required', 'boolean'],
        ]);
    
        $score = Score::create($validated);
        return response()->json($score, 201);
    }
    
}
