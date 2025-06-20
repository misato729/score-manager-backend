<?php

namespace App\Http\Controllers;

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
    
        // チェックイン登録（created_at に訪問時刻が自動で入る）
        UserVisit::create([
            'user_id' => $userId,
            'shop_id' => $shopId,
        ]);
    
        return response()->json(['message' => 'チェックインが完了しました'], 201);
    }
    
    // 行脚済み店舗一覧取得    
    public function index(Request $request)
{
    $userId = $request->user()->id;

    $visited = UserVisit::with('shop')
        ->where('user_id', $userId)
        ->orderBy('created_at', 'desc')

        ->get();

    return response()->json($visited);
}


// 任意ユーザーの行脚店舗一覧（非ログインでも取得可能）
public function publicIndex(Request $request)
{
    $userId = $request->query('user');

    if (!$userId || !is_numeric($userId)) {
        return response()->json(['message' => 'userパラメータが必要です'], 400);
    }

    $visited = UserVisit::with('shop')
        ->where('user_id', $userId)
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($visit) {
            return [
                'id' => $visit->shop->id,
                'name' => $visit->shop->name,
                'address' => $visit->shop->address,
                'visited_at' => $visit->visited_at,
            ];
        });

    return response()->json($visited);
}


}
