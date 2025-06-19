<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserVisit;
use App\Models\Shop;

class VisitController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'shop_id' => 'required|exists:shops,id',
        ]);

        $userId = $request->user()->id;
        $shopId = $request->input('shop_id');

        // すでにチェックイン済みか？
        $alreadyVisited = UserVisit::where('user_id', $userId)
            ->where('shop_id', $shopId)
            ->exists();

        if ($alreadyVisited) {
            return response()->json(['message' => 'すでにチェックイン済みです'], 409);
        }

        // チェックイン登録
        UserVisit::create([
            'user_id' => $userId,
            'shop_id' => $shopId,
            'visited_at' => now(),
        ]);

        return response()->json(['message' => 'チェックインが完了しました'], 201);
    }
}
